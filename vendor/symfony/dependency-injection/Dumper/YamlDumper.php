<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RectorPrefix20211211\Symfony\Component\DependencyInjection\Dumper;

use RectorPrefix20211211\Symfony\Component\DependencyInjection\Alias;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\ContainerInterface;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Definition;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Exception\LogicException;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Exception\RuntimeException;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Parameter;
use RectorPrefix20211211\Symfony\Component\DependencyInjection\Reference;
use RectorPrefix20211211\Symfony\Component\ExpressionLanguage\Expression;
use RectorPrefix20211211\Symfony\Component\Yaml\Dumper as YmlDumper;
use RectorPrefix20211211\Symfony\Component\Yaml\Parser;
use RectorPrefix20211211\Symfony\Component\Yaml\Tag\TaggedValue;
use RectorPrefix20211211\Symfony\Component\Yaml\Yaml;
/**
 * YamlDumper dumps a service container as a YAML string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class YamlDumper extends \RectorPrefix20211211\Symfony\Component\DependencyInjection\Dumper\Dumper
{
    private $dumper;
    /**
     * Dumps the service container as an YAML string.
     *
     * @return string
     */
    public function dump(array $options = [])
    {
        if (!\class_exists(\RectorPrefix20211211\Symfony\Component\Yaml\Dumper::class)) {
            throw new \RectorPrefix20211211\Symfony\Component\DependencyInjection\Exception\LogicException('Unable to dump the container as the Symfony Yaml Component is not installed.');
        }
        if (null === $this->dumper) {
            $this->dumper = new \RectorPrefix20211211\Symfony\Component\Yaml\Dumper();
        }
        return $this->container->resolveEnvPlaceholders($this->addParameters() . "\n" . $this->addServices());
    }
    private function addService(string $id, \RectorPrefix20211211\Symfony\Component\DependencyInjection\Definition $definition) : string
    {
        $code = "    {$id}:\n";
        if ($class = $definition->getClass()) {
            if ('\\' === \substr($class, 0, 1)) {
                $class = \substr($class, 1);
            }
            $code .= \sprintf("        class: %s\n", $this->dumper->dump($class));
        }
        if (!$definition->isPrivate()) {
            $code .= \sprintf("        public: %s\n", $definition->isPublic() ? 'true' : 'false');
        }
        $tagsCode = '';
        foreach ($definition->getTags() as $name => $tags) {
            foreach ($tags as $attributes) {
                $att = [];
                foreach ($attributes as $key => $value) {
                    $att[] = \sprintf('%s: %s', $this->dumper->dump($key), $this->dumper->dump($value));
                }
                $att = $att ? ': { ' . \implode(', ', $att) . ' }' : '';
                $tagsCode .= \sprintf("            - %s%s\n", $this->dumper->dump($name), $att);
            }
        }
        if ($tagsCode) {
            $code .= "        tags:\n" . $tagsCode;
        }
        if ($definition->getFile()) {
            $code .= \sprintf("        file: %s\n", $this->dumper->dump($definition->getFile()));
        }
        if ($definition->isSynthetic()) {
            $code .= "        synthetic: true\n";
        }
        if ($definition->isDeprecated()) {
            $code .= "        deprecated:\n";
            foreach ($definition->getDeprecation('%service_id%') as $key => $value) {
                if ('' !== $value) {
                    $code .= \sprintf("            %s: %s\n", $key, $this->dumper->dump($value));
                }
            }
        }
        if ($definition->isAutowired()) {
            $code .= "        autowire: true\n";
        }
        if ($definition->isAutoconfigured()) {
            $code .= "        autoconfigure: true\n";
        }
        if ($definition->isAbstract()) {
            $code .= "        abstract: true\n";
        }
        if ($definition->isLazy()) {
            $code .= "        lazy: true\n";
        }
        if ($definition->getArguments()) {
            $code .= \sprintf("        arguments: %s\n", $this->dumper->dump($this->dumpValue($definition->getArguments()), 0));
        }
        if ($definition->getProperties()) {
            $code .= \sprintf("        properties: %s\n", $this->dumper->dump($this->dumpValue($definition->getProperties()), 0));
        }
        if ($definition->getMethodCalls()) {
            $code .= \sprintf("        calls:\n%s\n", $this->dumper->dump($this->dumpValue($definition->getMethodCalls()), 1, 12));
        }
        if (!$definition->isShared()) {
            $code .= "        shared: false\n";
        }
        if (null !== ($decoratedService = $definition->getDecoratedService())) {
            [$decorated, $renamedId, $priority] = $decoratedService;
            $code .= \sprintf("        decorates: %s\n", $decorated);
            if (null !== $renamedId) {
                $code .= \sprintf("        decoration_inner_name: %s\n", $renamedId);
            }
            if (0 !== $priority) {
                $code .= \sprintf("        decoration_priority: %s\n", $priority);
            }
            $decorationOnInvalid = $decoratedService[3] ?? \RectorPrefix20211211\Symfony\Component\DependencyInjection\ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            if (\in_array($decorationOnInvalid, [\RectorPrefix20211211\Symfony\Component\DependencyInjection\ContainerInterface::IGNORE_ON_INVALID_REFERENCE, \RectorPrefix20211211\Symfony\Component\DependencyInjection\ContainerInterface::NULL_ON_INVALID_REFERENCE])) {
                $invalidBehavior = \RectorPrefix20211211\Symfony\Component\DependencyInjection\ContainerInterface::NULL_ON_INVALID_REFERENCE === $decorationOnInvalid ? 'null' : 'ignore';
                $code .= \sprintf("        decoration_on_invalid: %s\n", $invalidBehavior);
            }
        }
        if ($callable = $definition->getFactory()) {
            $code .= \sprintf("        factory: %s\n", $this->dumper->dump($this->dumpCallable($callable), 0));
        }
        if ($callable = $definition->getConfigurator()) {
            $code .= \sprintf("        configurator: %s\n", $this->dumper->dump($this->dumpCallable($callable), 0));
        }
        return $code;
    }
    private function addServiceAlias(string $alias, \RectorPrefix20211211\Symfony\Component\DependencyInjection\Alias $id) : string
    {
        $deprecated = '';
        if ($id->isDeprecated()) {
            $deprecated = "        deprecated:\n";
            foreach ($id->getDeprecation('%alias_id%') as $key => $value) {
                if ('' !== $value) {
                    $deprecated .= \sprintf("            %s: %s\n", $key, $value);
                }
            }
        }
        if (!$id->isDeprecated() && $id->isPrivate()) {
            return \sprintf("    %s: '@%s'\n", $alias, $id);
        }
        if ($id->isPublic()) {
            $deprecated = "        public: true\n" . $deprecated;
        }
        return \sprintf("    %s:\n        alias: %s\n%s", $alias, $id, $deprecated);
    }
    private function addServices() : string
    {
        if (!$this->container->getDefinitions()) {
            return '';
        }
        $code = "services:\n";
        foreach ($this->container->getDefinitions() as $id => $definition) {
            $code .= $this->addService($id, $definition);
        }
        $aliases = $this->container->getAliases();
        foreach ($aliases as $alias => $id) {
            while (isset($aliases[(string) $id])) {
                $id = $aliases[(string) $id];
            }
            $code .= $this->addServiceAlias($alias, $id);
        }
        return $code;
    }
    private function addParameters() : string
    {
        if (!$this->container->getParameterBag()->all()) {
            return '';
        }
        $parameters = $this->prepareParameters($this->container->getParameterBag()->all(), $this->container->isCompiled());
        return $this->dumper->dump(['parameters' => $parameters], 2);
    }
    /**
     * Dumps callable to YAML format.
     *
     * @param mixed $callable
     *
     * @return mixed
     */
    private function dumpCallable($callable)
    {
        if (\is_array($callable)) {
            if ($callable[0] instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Reference) {
                $callable = [$this->getServiceCall((string) $callable[0], $callable[0]), $callable[1]];
            } else {
                $callable = [$callable[0], $callable[1]];
            }
        }
        return $callable;
    }
    /**
     * Dumps the value to YAML format.
     *
     * @return mixed
     *
     * @throws RuntimeException When trying to dump object or resource
     */
    private function dumpValue($value)
    {
        if ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument) {
            $value = $value->getValues()[0];
            return new \RectorPrefix20211211\Symfony\Component\Yaml\Tag\TaggedValue('service_closure', $this->getServiceCall((string) $value, $value));
        }
        if ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\ArgumentInterface) {
            $tag = $value;
            if ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument || $value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument && ($tag = $value->getTaggedIteratorArgument())) {
                if (null === $tag->getIndexAttribute()) {
                    $content = $tag->getTag();
                } else {
                    $content = ['tag' => $tag->getTag(), 'index_by' => $tag->getIndexAttribute()];
                    if (null !== $tag->getDefaultIndexMethod()) {
                        $content['default_index_method'] = $tag->getDefaultIndexMethod();
                    }
                    if (null !== $tag->getDefaultPriorityMethod()) {
                        $content['default_priority_method'] = $tag->getDefaultPriorityMethod();
                    }
                }
                return new \RectorPrefix20211211\Symfony\Component\Yaml\Tag\TaggedValue($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument ? 'tagged_iterator' : 'tagged_locator', $content);
            }
            if ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\IteratorArgument) {
                $tag = 'iterator';
            } elseif ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument) {
                $tag = 'service_locator';
            } else {
                throw new \RectorPrefix20211211\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Unspecified Yaml tag for type "%s".', \get_debug_type($value)));
            }
            return new \RectorPrefix20211211\Symfony\Component\Yaml\Tag\TaggedValue($tag, $this->dumpValue($value->getValues()));
        }
        if (\is_array($value)) {
            $code = [];
            foreach ($value as $k => $v) {
                $code[$k] = $this->dumpValue($v);
            }
            return $code;
        } elseif ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Reference) {
            return $this->getServiceCall((string) $value, $value);
        } elseif ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Parameter) {
            return $this->getParameterCall((string) $value);
        } elseif ($value instanceof \RectorPrefix20211211\Symfony\Component\ExpressionLanguage\Expression) {
            return $this->getExpressionCall((string) $value);
        } elseif ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Definition) {
            return new \RectorPrefix20211211\Symfony\Component\Yaml\Tag\TaggedValue('service', (new \RectorPrefix20211211\Symfony\Component\Yaml\Parser())->parse("_:\n" . $this->addService('_', $value), \RectorPrefix20211211\Symfony\Component\Yaml\Yaml::PARSE_CUSTOM_TAGS)['_']['_']);
        } elseif ($value instanceof \RectorPrefix20211211\UnitEnum) {
            return new \RectorPrefix20211211\Symfony\Component\Yaml\Tag\TaggedValue('php/const', \sprintf('%s::%s', \get_class($value), $value->name));
        } elseif ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Argument\AbstractArgument) {
            return new \RectorPrefix20211211\Symfony\Component\Yaml\Tag\TaggedValue('abstract', $value->getText());
        } elseif (\is_object($value) || \is_resource($value)) {
            throw new \RectorPrefix20211211\Symfony\Component\DependencyInjection\Exception\RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
        }
        return $value;
    }
    private function getServiceCall(string $id, \RectorPrefix20211211\Symfony\Component\DependencyInjection\Reference $reference = null) : string
    {
        if (null !== $reference) {
            switch ($reference->getInvalidBehavior()) {
                case \RectorPrefix20211211\Symfony\Component\DependencyInjection\ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE:
                    break;
                case \RectorPrefix20211211\Symfony\Component\DependencyInjection\ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE:
                    break;
                case \RectorPrefix20211211\Symfony\Component\DependencyInjection\ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE:
                    return \sprintf('@!%s', $id);
                default:
                    return \sprintf('@?%s', $id);
            }
        }
        return \sprintf('@%s', $id);
    }
    private function getParameterCall(string $id) : string
    {
        return \sprintf('%%%s%%', $id);
    }
    private function getExpressionCall(string $expression) : string
    {
        return \sprintf('@=%s', $expression);
    }
    private function prepareParameters(array $parameters, bool $escape = \true) : array
    {
        $filtered = [];
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $value = $this->prepareParameters($value, $escape);
            } elseif ($value instanceof \RectorPrefix20211211\Symfony\Component\DependencyInjection\Reference || \is_string($value) && \strncmp($value, '@', \strlen('@')) === 0) {
                $value = '@' . $value;
            }
            $filtered[$key] = $value;
        }
        return $escape ? $this->escape($filtered) : $filtered;
    }
    private function escape(array $arguments) : array
    {
        $args = [];
        foreach ($arguments as $k => $v) {
            if (\is_array($v)) {
                $args[$k] = $this->escape($v);
            } elseif (\is_string($v)) {
                $args[$k] = \str_replace('%', '%%', $v);
            } else {
                $args[$k] = $v;
            }
        }
        return $args;
    }
}

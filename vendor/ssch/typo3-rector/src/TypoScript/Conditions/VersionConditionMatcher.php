<?php

declare (strict_types=1);
namespace Ssch\TYPO3Rector\TypoScript\Conditions;

use RectorPrefix20210521\Nette\Utils\Strings;
use Ssch\TYPO3Rector\Contract\TypoScript\Conditions\TyposcriptConditionMatcher;
final class VersionConditionMatcher implements \Ssch\TYPO3Rector\Contract\TypoScript\Conditions\TyposcriptConditionMatcher
{
    /**
     * @var string
     */
    private const TYPE = 'version';
    public function change(string $condition) : ?string
    {
        return null;
    }
    public function shouldApply(string $condition) : bool
    {
        return \RectorPrefix20210521\Nette\Utils\Strings::startsWith($condition, self::TYPE);
    }
}
<?php

declare (strict_types=1);
namespace RectorPrefix20211207;

require __DIR__ . '/../src/tracy.php';
use RectorPrefix20211207\Tracy\Debugger;
// For security reasons, Tracy is visible only on localhost.
// You may force Tracy to run in development mode by passing the Debugger::DEVELOPMENT instead of Debugger::DETECT.
\RectorPrefix20211207\Tracy\Debugger::enable(\RectorPrefix20211207\Tracy\Debugger::DETECT, __DIR__ . '/log');
\RectorPrefix20211207\Tracy\Debugger::$strictMode = \true;
?>
<!DOCTYPE html><link rel="stylesheet" href="assets/style.css">

<h1>Tracy Notice and StrictMode demo</h1>

<?php 
function foo($from)
{
    echo $form;
}
\RectorPrefix20211207\foo(123);
if (\RectorPrefix20211207\Tracy\Debugger::$productionMode) {
    echo '<p><b>For security reasons, Tracy is visible only on localhost. Look into the source code to see how to enable Tracy.</b></p>';
}

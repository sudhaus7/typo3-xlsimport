<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

require_once __DIR__ . '/../../.Build/vendor/autoload.php';

/**
 * This script checks tests do not start with "test" like
 * "public function testSomething". Instead, they should be
 *  like "public function SomethingIsLike()" and have a @test annotation.
 */
class NodeVisitor extends NodeVisitorAbstract
{
    public array $matches = [];

    public function enterNode(Node $node): void
    {
        if (($node instanceof Node\Stmt\ClassMethod) && str_starts_with($node->name->name, 'test')) {
            $this->matches[$node->getLine()] = $node->name->name;
        }
    }
}

if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() >= 13) {
    $parser = (new ParserFactory())->createForVersion(\PhpParser\PhpVersion::fromComponents(8, 1));
} else {
    $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
}

$finder = new Symfony\Component\Finder\Finder();
$finder->files()
    ->in([
        __DIR__ . '/../../Tests/Unit/',
        __DIR__ . '/../../Tests/Functional/',
    ])
    ->name('/Test\.php$/');

$output = new ConsoleOutput();

$errors = [];
foreach ($finder as $file) {
    try {
        $ast = $parser->parse($file->getContents());
    } catch (Error $error) {
        $output->writeln('<error>Parse error: ' . $error->getMessage() . '</error>');
        exit(1);
    }

    $visitor = new NodeVisitor();

    $traverser = new NodeTraverser();
    $traverser->addVisitor($visitor);

    $ast = $traverser->traverse($ast);

    if (!empty($visitor->matches)) {
        $errors[$file->getRealPath()] = $visitor->matches;
        $output->write('<error>F</error>');
    } else {
        $output->write('<fg=green>.</>');
    }
}

$output->writeln('');

if (!empty($errors)) {
    $output->writeln('');

    foreach ($errors as $file => $matchesPerLine) {
        $output->writeln('');
        $output->writeln('<error>At least on method starts with "test" in ' . $file . '</error>');

        /**
         * @var array $matchesPerLine
         * @var int $line
         * @var array $matches
         */
        foreach ($matchesPerLine as $line => $methodName) {
            $output->writeln('Method:' . $methodName . ' Line:' . $line);
        }
    }
    exit(1);
}

exit(0);

<?php

declare(strict_types=1);

use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use Rector\Core\Console\Formatter\ColorConsoleDiffFormatter;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Webmozart\Assert\Assert;

require __DIR__ . '/../vendor/autoload.php';

// @todo replace regex in twigs

// @todo create rector rules
// @todo ask GPT to help with that :)
// before + after

// 1. convert twig to blade

// @todo create a command from this one :)
// make both models live together B-)

final class TwigToBladeConverter
{
    /**
     * @var array<string, string>
     */
    private const TWIG_TO_BLADE_REPLACE_REGEXES = [
        // layout
        '#{\% extends "(.*?)\.twig" \%\}#' => '@extends(\'$1\')',
        '#{\% block (.*?) \%}#' => '@section(\'$1\')',
        '#{\% endblock \%}#' => '@endsection',

        '#{\% include (\'|\")(.*?)\.twig(\'|\") \%}#' => '@include(\'$2\')',

        // control structures
        '#{% if (?<condition>.*?) %}#' => '@if ($1)',
        '#{% for (?<singular>.*?) in (?<plural>.*?) %}#' => '@foreach ($$2 as $$1)',
        '#{% else %}#' => '@else ',
        '#{% endif %}#' => '@endif',
        '#{% endfor %}#' => '@endforeach',
        '#\{\# @var (?<variable>.*?) (?<type>.*?) \#\}#' => '@php /** @var $$1 $2 */ @endphp',
        '#path\((.*?)\)#' => 'route($1)',
        '#\{ (?<key>\w+)\: (?<value>.*?) \}#' => '[\'$1\' => $2]',

        // variables
        '#{{ (?<variable>\w+)\.(?<method>\w+) }}#' => '{{ $$1->$2 }}',
        '#{{ (?<variable>\w+) }}#' => '{{ $$1 }}',
        '#{{ (?<variable>\w+)\|(?<filter>\w+) }}#' => '{{ $2($$1) }}',
    ];

    public function __construct(
        private readonly Differ $differ = new Differ(new UnifiedDiffOutputBuilder()),
        private readonly ColorConsoleDiffFormatter $colorConsoleDiffFormatter = new ColorConsoleDiffFormatter(),
        private readonly SymfonyStyle $symfonyStyle = new SymfonyStyle(new ArrayInput([]), new ConsoleOutput()),
    ) {
    }

    public function run(string $templatesDirectory): void
    {
        if (! file_exists($templatesDirectory)) {
            return;
        }

        $twigFilePaths = $this->findTwigFilePaths($templatesDirectory);

        foreach ($twigFilePaths as $twigFilePath) {
            $bladeFilePath = substr($twigFilePath, 0, -5) . '.blade.php';

            $twigFileContents = FileSystem::read($twigFilePath);
            $bladeFileContents = $twigFileContents;

            foreach (self::TWIG_TO_BLADE_REPLACE_REGEXES as $twigRegex => $bladeReplacement) {
                $bladeFileContents = Strings::replace($bladeFileContents, $twigRegex, $bladeReplacement);
            }

            // nothing to change
            if ($twigFileContents === $bladeFileContents) {
                continue;
            }

            $diff = $this->differ->diff($twigFileContents, $bladeFileContents);
            $colorDiff = $this->colorConsoleDiffFormatter->format($diff);
            $this->symfonyStyle->writeln($colorDiff);

            FileSystem::write($bladeFilePath, $bladeFileContents);
        }
    }

    /**
     * @return string[]
     */
    private function findTwigFilePaths(string $templatesDirectory): array
    {
        /** @var string[] $twigFilePaths */
        $twigFilePaths = glob($templatesDirectory . '/*/*.twig');
        Assert::allString($twigFilePaths);

        // use realpaths
        return array_map(function (string $twigFilePath): string {
            return realpath($twigFilePath);
        }, $twigFilePaths);
    }
}


$twigToBladeConverter = new TwigToBladeConverter();
$twigToBladeConverter->run(__DIR__ . '/../resources/views');
<?php
declare(strict_types=1);

namespace CodeX\View;

use RuntimeException;
use Throwable;

class Compiler
{
    const int MAX_DEPTH = 50;
    public static array $includedTemplates = [];
    public static array $currentDependencies = [];
    private static array $customDirectives = [];
    private static array $filters = [];
    private static array $compilationCache = [];
    private static array $stringPlaceholders = [];
    private static int $placeholderCounter = 0;
    private static array $compilationStack = [];
    private static int $depth = 0;

    public static function getCurrentDependencies(): array
    {
        return self::$currentDependencies;
    }

    public static function reset(): void
    {
        self::$includedTemplates = [];
        self::$currentDependencies = [];
    }

    public static function clearCache(): void
    {
        self::$compilationCache = [];
        self::$includedTemplates = [];
        self::$currentDependencies = [];
        self::$stringPlaceholders = [];
        self::$placeholderCounter = 0;
    }

    public static function directive(string $name, callable $handler): void
    {
        self::$customDirectives[$name] = $handler;
    }

    public static function filter(string $name, callable $handler): void
    {
        self::$filters[$name] = $handler;
    }

    public static function compile(string $source, string $currentTemplate, string $viewPath): string
    {


// Проверка на циклические зависимости
        if (in_array($currentTemplate, self::$compilationStack, true)) {
            $stack = implode(' -> ', self::$compilationStack) . ' -> ' . $currentTemplate;
            throw new RuntimeException("Обнаружена циклическая зависимость в шаблонах: {$stack}");
        }

        if (self::$depth > self::MAX_DEPTH) {
            throw new RuntimeException("Превышена максимальная глубина компиляции шаблонов: " . self::MAX_DEPTH);
        }

        $cacheKey = md5($source . $currentTemplate);

        if (isset(self::$compilationCache[$cacheKey])) {
            return self::$compilationCache[$cacheKey];
        }

        self::$compilationStack[] = $currentTemplate;
        self::$depth++;

        try {
            self::$includedTemplates = [];
            self::$currentDependencies = [$currentTemplate];

// Обработка @verbatim блоков в первую очередь
            $source = self::processVerbatimBlocks($source);

// Удаляем Blade-подобные комментарии
            $source = preg_replace('/{{--.*?--}}/s', '', $source);

// Разбиваем на строки
            $lines = explode("\n", $source);
            $compiledLines = [];
            $inSection = false;
            $sectionContent = '';
            $sectionName = '';
            $sectionStack = [];

            foreach ($lines as $lineNumber => $line) {
                $trimmedLine = trim($line);

                try {
// Обработка @section
                    if (preg_match("/@section\s*\(\s*['\"](.+?)['\"]\s*\)/", $trimmedLine, $matches)) {
                        if ($inSection) {
                            throw new RuntimeException("Вложенные секции не поддерживаются. Секция '{$sectionName}' не закрыта перед началом '{$matches[1]}'");
                        }
                        $inSection = true;
                        $sectionName = $matches[1];
                        $sectionStack[] = $sectionName;
                        $sectionContent = '';
                        continue;
                    }

// Обработка @endsection
                    if (preg_match("/^@endsection\s*(?:#.*)?$/", $trimmedLine)) {
                        if ($inSection) {
                            $compiledContent = self::compileDirectives($sectionContent);
                            $compiledLines[] = '<?php $view->startSection("' . $sectionName . '"); ?>' . $compiledContent . '<?php $view->endSection(); ?>';
                            $inSection = false;
                            $sectionContent = '';
                            array_pop($sectionStack);
                        } else {
// Игнорируем @endsection без открытой секции вместо ошибки
                            $compiledLines[] = "<!-- @endsection без @section проигнорирован -->";
                        }
                        continue;
                    }

// Обработка @extends
                    if (preg_match("/@extends\s*\(\s*['\"](.+?)['\"]\s*\)/", $trimmedLine, $matches)) {
                        $compiledLines[] = '<?php $view->extendsLayout("' . $matches[1] . '"); ?>';
                        continue;
                    }

// Обработка @yield с улучшенной поддержкой строк с пробелами
                    if (preg_match("/@yield\s*\(\s*['\"](.+?)['\"]\s*(?:,\s*(.+?))?\s*\)/", $trimmedLine, $matches)) {
                        $sectionName = $matches[1];
                        $default = "''";

                        if (isset($matches[2])) {
// Обрабатываем значение по умолчанию, которое может содержать пробелы
                            $defaultValue = trim($matches[2]);
                            if (preg_match("/^['\"](.*)['\"]$/", $defaultValue, $defaultMatches)) {
// Если значение в кавычках, извлекаем его и экранируем
                                $default = "'" . addslashes($defaultMatches[1]) . "'";
                            } else {
// Если нет кавычек, используем как есть (для переменных)
                                $default = $defaultValue;
                            }
                        }

                        $compiledLines[] = '<?php echo $view->yieldSection("' . $sectionName . '", ' . $default . '); ?>';
                        continue;
                    }

// Обработка остального контента
                    if ($inSection) {
                        $sectionContent .= $line . "\n";
                    } else {
                        $compiledLine = self::compileDirectives($line);
                        $compiledLines[] = $compiledLine;
                    }
                } catch (RuntimeException $e) {
                    throw new RuntimeException("Ошибка компиляции в строке " . ($lineNumber + 1) . ": " . $e->getMessage());
                }
            }

// Проверяем незакрытые секции
            if ($inSection) {
                throw new RuntimeException("Незакрытая секция: '{$sectionName}'");
            }

            if (!empty($sectionStack)) {
                throw new RuntimeException("Незакрытые секции: " . implode(', ', $sectionStack));
            }

            self::$currentDependencies = array_unique(array_merge(self::$currentDependencies, self::$includedTemplates));

            $result = implode("\n", $compiledLines);
            self::$compilationCache[$cacheKey] = $result;

            return $result;
        } finally {
            array_pop(self::$compilationStack);
            self::$depth--;
        }
    }

    private static function processVerbatimBlocks(string $source): string
    {
        return preg_replace_callback('/@verbatim\s*(.*?)@endverbatim/s', static function ($matches) {
            return $matches[1];
        }, $source);
    }

    private static function compileDirectives(string $source): string
    {
        $originalSource = $source;

        try {
// Обработка пользовательских директив
            foreach (self::$customDirectives as $name => $handler) {
                $pattern = '/@' . preg_quote($name, '/') . '(?:\s*\((.*?)\))?/s';
                $source = preg_replace_callback($pattern, static function ($matches) use ($handler, $name) {
                    $params = $matches[1] ?? '';
                    $result = $handler($params);
                    if (!str_starts_with(trim($result), '<?')) {
                        return '<?php ' . $result . ' ?>';
                    }
                    return $result;
                }, $source);
            }

// Обработка @csrf
            $source = preg_replace_callback('/@csrf(?=\s|$)/', static function ($matches) {
                return '<?= \\CodeX\\View::csrfField() ?>';
            }, $source);

// Обработка {{ }} с экранированием
            $source = preg_replace_callback('/{{\s*(.+?)\s*}}/s', static function ($matches) {
                $expression = trim($matches[1]);
                return self::compileEcho($expression);
            }, $source);

// Обработка {!! !!} без экранирования
            $source = preg_replace_callback('/{!!\s*(.+?)\s*!!}/s', static function ($matches) {
                return '<?= ' . trim($matches[1]) . ' ?>';
            }, $source);

// Обработка @json
            $source = preg_replace_callback('/@json\s*\(\s*(.+?)(?:\s*,\s*(.+?))?\s*\)/s', static function ($matches) {
                $expression = trim($matches[1]);
                $flags = isset($matches[2]) ? trim($matches[2]) : 'JSON_THROW_ON_ERROR';
                return '<?= \\CodeX\\View::toJson(' . $expression . ', ' . $flags . ') ?>';
            }, $source);

// Структурные директивы
            $directives = [// Условные операторы
                '/@if\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/' => '<?php if ($1): ?>', '/@elseif\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/' => '<?php elseif ($1): ?>', '/@else\b/' => '<?php else: ?>', '/@endif\b/' => '<?php endif; ?>',

// Циклы
                '/@foreach\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/' => '<?php foreach ($1): ?>', '/@endforeach\b/' => '<?php endforeach; ?>', '/@for\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/' => '<?php for ($1): ?>', '/@endfor\b/' => '<?php endfor; ?>', '/@while\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/' => '<?php while ($1): ?>', '/@endwhile\b/' => '<?php endwhile; ?>',

// Break/Continue
                '/@break\b/' => '<?php break; ?>', '/@continue\b/' => '<?php continue; ?>',

// Авторизация
                '/@auth\b/' => '<?php if (\\CodeX\\View::checkAuth()): ?>', '/@endauth\b/' => '<?php endif; ?>', '/@guest\b/' => '<?php if (!\\CodeX\\View::checkAuth()): ?>', '/@endguest\b/' => '<?php endif; ?>',];

            foreach ($directives as $pattern => $replacement) {
                $source = preg_replace($pattern, $replacement, $source);
            }

// Обработка @parent для наследования секций
            $source = preg_replace_callback('/@parent\b/', static function ($matches) {
                return '<?php echo $view->yieldSection($view->currentSection); ?>';
            }, $source);

// Обработка @can/@cannot с улучшенным парсингом
            $source = preg_replace_callback("/@can\s*\(\s*([\"'])(.*?)\\1\s*(?:,\s*(.*?))?\s*\)/s", static function ($matches) {
                $ability = $matches[2];
                $arguments = isset($matches[3]) ? ', ' . trim($matches[3]) : '';
                return '<?php if (\\CodeX\\Auth\\Gate::allows("' . $ability . '"' . $arguments . ')): ?>';
            }, $source);

            $source = preg_replace_callback("/@cannot\s*\(\s*([\"'])(.*?)\\1\s*(?:,\s*(.*?))?\s*\)/s", static function ($matches) {
                $ability = $matches[2];
                $arguments = isset($matches[3]) ? ', ' . trim($matches[3]) : '';
                return '<?php if (\\CodeX\\Auth\\Gate::denies("' . $ability . '"' . $arguments . ')): ?>';
            }, $source);

            $source = preg_replace('/@endcan\b/', '<?php endif; ?>', $source);
            $source = preg_replace('/@endcannot\b/', '<?php endif; ?>', $source);

// Компоненты
            $source = preg_replace_callback("/@component\s*\(\s*([\"'])(.+?)\\1\s*(?:,\s*(.*?))?\s*\)/s", static function ($matches) {
                $component = $matches[2];
                $data = isset($matches[3]) ? trim($matches[3]) : '[]';
                return "<?php \$view->startComponent('{$component}', {$data}); ?>";
            }, $source);

            $source = preg_replace('/@endcomponent\b/', '<?php echo $view->renderComponent(); ?>', $source);

// Слоты компонентов
            $source = preg_replace_callback("/@slot\s*\(\s*([\"'])(.+?)\\1\s*\)/s", static function ($matches) {
                return "<?php \$view->slot('{$matches[2]}'); ?>";
            }, $source);

            $source = preg_replace('/@endslot\b/', '<?php $view->endSlot(); ?>', $source);

// Специальные директивы для слотов компонентов (без экранирования)
            $source = preg_replace_callback('/@componentSlot\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.*?)[\'"]\s*)?\)/', static function ($matches) {
                $slotName = $matches[1];
                $default = isset($matches[2]) ? "'" . addslashes($matches[2]) . "'" : "''";
                return '<?= $component->getSlot("' . $slotName . '", ' . $default . ') ?>';
            }, $source);

            $source = preg_replace_callback('/@hasComponentSlot\s*\(\s*[\'"](.+?)[\'"]\s*\)/', static function ($matches) {
                $slotName = $matches[1];
                return '<?php if ($component->hasSlot("' . $slotName . '")): ?>';
            }, $source);

            $source = preg_replace('/@endHasComponentSlot/', '<?php endif; ?>', $source);

// Включения
            $source = preg_replace_callback("/@include\s*\(\s*([\"'])(.+?)\\1\s*(?:,\s*(.*?))?\s*\)/s", static function ($matches) {
                $template = $matches[2];
                $data = isset($matches[3]) ? trim($matches[3]) : '[]';
                self::$includedTemplates[] = $template;
                return "<?= \$view->makePartial('{$template}', {$data}) ?>";
            }, $source);

// Push/Stack
            $source = preg_replace("/@push\s*\(\s*([\"'])(.+?)\\1\s*\)/", '<?php $view->startPush("$2"); ?>', $source);
            $source = preg_replace('/@endpush\b/', '<?php $view->endPush(); ?>', $source);
            $source = preg_replace("/@stack\s*\(\s*([\"'])(.+?)\\1\s*\)/", '<?php echo $view->yieldPush("$2"); ?>', $source);

// PHP блоки
            $source = preg_replace_callback('/@php\s*(.*?)@endphp/s', static function ($matches) {
                return '<?php ' . $matches[1] . ' ?>';
            }, $source);

            return $source;
        } catch (Throwable $e) {
            throw new RuntimeException("Ошибка компиляции директив в выражении: " . substr($originalSource, 0, 100) . "... Подробности: " . $e->getMessage());
        }
    }

    private static function compileEcho(string $expression): string
    {
// Предварительная обработка строковых литералов
        $expression = self::preserveStringLiterals($expression);

        if (!str_contains($expression, '|')) {
            $expression = self::restoreStringLiterals($expression);
            return '<?= \\CodeX\\View::e(' . $expression . ') ?>';
        }

        $filters = self::parseFilters($expression);
        $value = array_shift($filters);
        $value = self::restoreStringLiterals($value);

        foreach ($filters as $filter) {
            $filter = self::restoreStringLiterals(trim($filter));

            if (preg_match('/^([a-zA-Z_]\w*)\s*\((.*)\)$/', $filter, $matches)) {
                [$filterName, $filterArgs] = $matches;

                if (isset(self::$filters[$filterName])) {
                    $value = self::$filters[$filterName]($value, $filterArgs);
                } else {
                    throw new RuntimeException("Фильтр '{$filterName}' не зарегистрирован. Используйте Compiler::filter() для регистрации.");
                }
            } else {
                $filterName = $filter;
                if (isset(self::$filters[$filterName])) {
                    $value = self::$filters[$filterName]($value);
                } else {
                    throw new RuntimeException("Фильтр '{$filterName}' не зарегистрирован. Используйте Compiler::filter() для регистрации.");
                }
            }
        }

        return '<?= \\CodeX\\View::e(' . $value . ') ?>';
    }

    private static function preserveStringLiterals(string $expression): string
    {
        return preg_replace_callback('/([\'"])(?:\\\\.|(?!\\1).)*\\1/', static function ($matches) {
            $placeholder = "___STRING_" . self::$placeholderCounter . "___";
            self::$stringPlaceholders[$placeholder] = $matches[0];
            self::$placeholderCounter++;
            return $placeholder;
        }, $expression);
    }

    private static function restoreStringLiterals(string $expression): string
    {
        if (preg_match('/___STRING_(\d+)___/', $expression)) {
            $expression = str_replace(array_keys(self::$stringPlaceholders), array_values(self::$stringPlaceholders), $expression);
        }

        return $expression;
    }

    private static function parseFilters(string $expression): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $i = 0;
        $len = strlen($expression);

        while ($i < $len) {
            $char = $expression[$i];
            $prevChar = $i > 0 ? $expression[$i - 1] : '';

// Отслеживаем кавычки (игнорируем экранированные)
            if ($char === "'" && !$inDoubleQuote && !$inBacktick && $prevChar !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && !$inBacktick && $prevChar !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote && $prevChar !== '\\') {
                $inBacktick = !$inBacktick;
            }

// Отслеживаем скобки только вне строк
            if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
            }

            if ($char === '|' && $depth === 0 && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }

            $i++;
        }

        if ($current !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }
}
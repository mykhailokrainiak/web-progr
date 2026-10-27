<?php
declare(strict_types=1);

// XML-версія сховища: routes.xml, корінь <routes>, записи <route>.
require __DIR__ . '/../shared/routes.php';

const STORAGE_FORMAT = 'XML';
const STORAGE_FILE = __DIR__ . '/routes.xml';
const STORAGE_LOCK = __DIR__ . '/routes.lock';

function parseXml(string $text): DOMDocument
{
    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    try {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        // Підстановку сутностей і завантаження DTD не вмикаємо.
        if (!$dom->loadXML($text, LIBXML_NONET)) {
            throw new RuntimeException('Некоректний синтаксис XML.');
        }
        if ($dom->doctype !== null) {
            throw new RuntimeException('DTD у форматі каталогу не дозволено.');
        }
        return $dom;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/** Перетворює DOM-дерево на список записів із перевіркою структури. */
function routesFromXml(DOMDocument $dom): array
{
    $root = $dom->documentElement;
    if ($root === null || $root->tagName !== 'routes' || $root->attributes->length !== 0) {
        throw new RuntimeException('Очікується кореневий елемент routes без атрибутів.');
    }
    $routes = [];
    foreach ($root->childNodes as $node) {
        if ($node instanceof DOMText && trim($node->textContent) === '') {
            continue;
        }
        if (!$node instanceof DOMElement || $node->tagName !== 'route' || $node->attributes->length !== 0) {
            throw new RuntimeException('У routes дозволено лише елементи route без атрибутів.');
        }
        $fields = [];
        foreach ($node->childNodes as $field) {
            if ($field instanceof DOMText && trim($field->textContent) === '') {
                continue;
            }
            if (!$field instanceof DOMElement || !in_array($field->tagName, ROUTE_FIELDS, true)
                || array_key_exists($field->tagName, $fields) || $field->attributes->length !== 0) {
                throw new RuntimeException('Поля route: ' . implode(', ', ROUTE_FIELDS) . ' без повторів і атрибутів.');
            }
            foreach ($field->childNodes as $part) {
                if (!$part instanceof DOMText) {
                    throw new RuntimeException('Поле ' . $field->tagName . ' має містити лише текст.');
                }
            }
            $fields[$field->tagName] = $field->textContent;
        }
        // XML зберігає текст: числові поля перетворюємо після перевірки формату.
        foreach (['platform', 'price'] as $numeric) {
            if (isset($fields[$numeric])) {
                $fields[$numeric] = strictInt($fields[$numeric]) ?? $fields[$numeric];
            }
        }
        $ordered = [];
        foreach (ROUTE_FIELDS as $name) {
            if (array_key_exists($name, $fields)) {
                $ordered[$name] = $fields[$name];
            }
        }
        $routes[] = $ordered;
    }
    return validateRoutes($routes);
}

function routesToXml(array $routes): DOMDocument
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $root = $dom->createElement('routes');
    $dom->appendChild($root);
    foreach (validateRoutes($routes) as $route) {
        $node = $dom->createElement('route');
        // Імена елементів — лише з ROUTE_FIELDS, значення — текстовими вузлами.
        foreach (ROUTE_FIELDS as $name) {
            $field = $dom->createElement($name);
            $field->appendChild($dom->createTextNode((string) $route[$name]));
            $node->appendChild($field);
        }
        $root->appendChild($node);
    }
    return $dom;
}

function loadRoutes(): array
{
    return routesFromXml(parseXml(readText(STORAGE_FILE)));
}

/** Зберігає весь документ; повертає кількість записаних байтів. */
function saveRoutes(array $routes): int
{
    if (!is_file(STORAGE_FILE) || !is_writable(STORAGE_FILE)) {
        throw new RuntimeException('Файл даних недоступний для запису. Зміни не збережено.');
    }
    $bytes = routesToXml($routes)->save(STORAGE_FILE, LIBXML_NOEMPTYTAG);
    if ($bytes === false) {
        throw new RuntimeException('Не вдалося записати XML. Зміни не збережено.');
    }
    return $bytes;
}

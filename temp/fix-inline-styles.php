<?php
/**
 * インラインstyle属性を一括でCSSクラスに置き換えるスクリプト
 */

$replacements = [
    // Background colors
    'style="background: #eff6ff; border: 1px solid #93c5fd"' => 'class="bg-blue-50 border-blue-200"',
    'style="background: #f0fdf4; border: 1px solid #86efac"' => 'class="bg-green-50 border-green-200"',
    'style="background: #fef3c7; border: 1px solid #fbbf24"' => 'class="bg-yellow-50 border-yellow-200"',
    'style="background: #fee2e2; border: 1px solid #fca5a5"' => 'class="bg-red-50 border-red-200"',
    'style="background: #ecfdf5; border: 2px solid #22c55e"' => 'class="bg-green-light border-2-success"',
    'style="background: #dcfce7; border-radius: 6px"' => 'class="bg-green-100 rounded-6"',
    'style="background: #f9fafb; border-radius: 6px"' => 'class="bg-gray-50 rounded-6"',
    'style="background: #e3f2fd; border: 1px solid #90caf9"' => 'class="info-box-blue"',
    'style="background: #f0fdf4; border-radius: 6px"' => 'class="bg-green-50 rounded-6"',
    'style="background: var(--gray-50); border-radius: 6px"' => 'class="bg-gray-50 rounded-6"',
    'style="background: #f0f9ff; border: 1px solid #93c5fd"' => 'class="bg-blue-lighter border-blue-200"',

    // Button styles
    'style="background: #16a34a; color: white; border: none"' => 'class="bg-success"',
    'style="background: #8b5cf6; color: white; border: none"' => 'class="bg-purple"',
    'style="background: #3b82f6"' => 'class="btn-primary"',
    'style="background: #dc2626; color: white"' => 'class="bg-danger"',
    'style="background:#f5f5f5; color:#333"' => 'class="bg-light"',

    // Padding
    'style="padding: 0.5rem 1rem"' => 'class="p-05-10"',
    'style="padding: 0.75rem 1.5rem"' => 'class="p-075-15"',
    'style="padding: 0.75rem 2rem"' => 'class="p-075-20"',
    'style="padding: 0.5rem 0.75rem"' => 'class="p-05-075"',
    'style="padding:8px 16px"' => 'class="py-05 px-16"',
    'style="padding:12px 16px"' => 'class="p-12-16"',
    'style="padding: 24px 32px"' => 'class="p-24-32"',

    // Border radius
    'style="border-radius: 6px"' => 'class="rounded-6"',
    'style="border-radius: 8px"' => 'class="rounded-8"',
    'style="border-radius: 12px"' => 'class="rounded-12"',
    'style="border-radius:16px"' => 'class="rounded-16"',

    // Font sizes
    'style="font-size: 0.85rem"' => 'class="text-085"',
    'style="font-size: 0.9rem"' => 'class="text-09"',
    'style="font-size: 1rem"' => 'class="text-10"',
    'style="font-size: 1.1rem"' => 'class="text-11"',
    'style="font-size: 1.2rem"' => 'class="text-12"',
    'style="font-size:1.1rem"' => 'class="text-11"',

    // Width
    'style="width: auto"' => 'class="w-auto"',
    'style="min-width: 200px"' => 'class="min-w-200"',
    'style="min-width: 300px"' => 'class="min-w-300"',
    'style="max-width: 400px"' => 'class="max-w-400"',
    'style="max-width: 450px"' => 'class="max-w-450"',
    'style="max-width: 900px"' => 'class="max-w-900"',

    // Text decoration
    'style="text-decoration: underline"' => 'class="text-underline"',
    'style="white-space: pre-wrap"' => 'class="whitespace-pre-wrap"',

    // Display
    'style="display: contents"' => 'class="display-contents"',

    // Opacity
    'style="opacity: 0.6"' => 'class="opacity-06"',

    // Margins
    'style="margin-top: 0.75rem"' => 'class="mt-075"',
    'style="margin-left: 0.25rem"' => 'class="ml-025"',

    // Special cases - preserve if dynamic, remove if static
    'style="background:none; border:none"' => '', // Remove button reset style
];

$files = glob(__DIR__ . '/../pages/*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;

    foreach ($replacements as $search => $replace) {
        // For empty replace (removal), just remove the style attribute
        if ($replace === '') {
            $content = str_replace($search, '', $content);
        } else {
            // Check if class already exists
            $content = preg_replace_callback(
                '/<([a-z]+)([^>]*?)' . preg_quote($search, '/') . '([^>]*)>/i',
                function($matches) use ($replace) {
                    $tag = $matches[1];
                    $before = $matches[2];
                    $after = $matches[3];

                    // Extract class from replacement
                    preg_match('/class="([^"]*)"/', $replace, $classMatches);
                    $newClasses = $classMatches[1] ?? '';

                    // Check if there's already a class attribute
                    if (preg_match('/class="([^"]*)"/', $before . $after, $existingClass)) {
                        // Merge classes
                        $merged = trim($existingClass[1] . ' ' . $newClasses);
                        $result = $before . $after;
                        $result = preg_replace('/class="[^"]*"/', 'class="' . $merged . '"', $result, 1);
                        return '<' . $tag . $result . '>';
                    } else {
                        // Add new class attribute
                        return '<' . $tag . $before . ' ' . $replace . $after . '>';
                    }
                },
                $content
            );
        }
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo basename($file) . ": 置換完了\n";
    }
}

echo "\n完了しました。\n";

<?php

/**
 * Extensible design-format registry shared by GenerateDesignAction,
 * GenerateImageAction and their validation rules. Add a new ratio here and
 * it becomes selectable everywhere without touching business logic.
 *
 * `size` is the string passed to the image provider (OpenAI-style WxH).
 * Square is the default everywhere a design preview is shown — it must
 * always fill the frame with no letterboxing (see docs/PROMPTS.md).
 */
return [
    'default_format' => '1:1',

    'formats' => [
        '1:1' => [
            'label' => 'مربع',
            'width' => 1024,
            'height' => 1024,
            'size' => '1024x1024',
            'css_ratio' => '1 / 1',
        ],
        '4:5' => [
            'label' => 'عمودي',
            'width' => 1024,
            'height' => 1280,
            'size' => '1024x1536',
            'css_ratio' => '4 / 5',
        ],
        '9:16' => [
            'label' => 'ستوري',
            'width' => 1080,
            'height' => 1920,
            'size' => '1024x1536',
            'css_ratio' => '9 / 16',
        ],
    ],
];

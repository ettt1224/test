<?php

// 常用驗證訊息；需要其他規則時再補上（未列出者會顯示英文預設訊息）
return [
    'required' => ':attribute 為必填',
    'string' => ':attribute 必須是文字',
    'integer' => ':attribute 必須是整數',
    'numeric' => ':attribute 必須是數字',
    'array' => ':attribute 格式錯誤',
    'date' => ':attribute 不是有效的日期',
    'after_or_equal' => ':attribute 必須在 :date 之後或相同',
    'different' => ':attribute 與 :other 不可相同',
    'exists' => '所選的 :attribute 不存在',
    'unique' => ':attribute 已被使用',
    'enum' => '所選的 :attribute 無效',
    'file' => ':attribute 必須是檔案',
    'mimes' => ':attribute 必須是 :values 格式',
    'gt' => [
        'numeric' => ':attribute 必須大於 :value',
    ],
    'min' => [
        'numeric' => ':attribute 不可小於 :min',
    ],
    'max' => [
        'string' => ':attribute 不可超過 :max 個字',
        'file' => ':attribute 不可超過 :max KB',
    ],

    'attributes' => [],
];

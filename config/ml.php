<?php

$venvPython = PHP_OS_FAMILY === 'Windows'
    ? base_path('ml/.venv/Scripts/python.exe')
    : base_path('ml/.venv/bin/python');

return [
    'python' => env('ML_PYTHON', file_exists($venvPython) ? $venvPython : 'python'),
    'predict_script' => env('ML_PREDICT_SCRIPT', base_path('ml/predict.py')),
    'model_path' => env('ML_MODEL_PATH', base_path('ml/models/unet_seg_model.pth')),
    'input_size' => (int) env('ML_INPUT_SIZE', 512),
    'timeout' => (int) env('ML_TIMEOUT', 180),
];

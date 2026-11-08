<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIModel extends Model
{
    protected $table = 'ai_models';

    protected $fillable = [
        'name',
        'model_type',
        'version',
        'accuracy',
        'accuracy_metric',
        'training_date',
        'dataset_size',
        'status',
        'description',
        'model_path',
        'is_active'
    ];

    protected $casts = [
        'accuracy' => 'float',
        'dataset_size' => 'integer',
        'training_date' => 'datetime',
        'is_active' => 'boolean'
    ];

    /** Suggested names for what the accuracy figure measures (the field also accepts free text). */
    public const ACCURACY_METRICS = ['IoU', 'Dice / F1', 'Pixel accuracy', 'mAP50 (mask)', 'mAP50-95 (mask)', 'Precision', 'Recall'];

    /** Metric name used for figures read from a YOLO checkpoint (its mask mAP50 on the validation split). */
    public const YOLO_CHECKPOINT_METRIC = 'mAP50 (mask)';

    /**
     * Stored attributes from the validation results inside a YOLO checkpoint (see
     * MangroveSegmentationService::inspectGenusWeights): accuracy = mask mAP50, plus a description
     * naming the dataset (Roboflow export) and the other validation figures.
     *
     * @param  array<string, mixed>  $training
     * @param  array<int, string>  $classes
     * @return array<string, mixed>
     */
    public static function attributesFromYoloTraining(array $training, array $classes = []): array
    {
        $percent = fn ($value) => is_numeric($value) ? round((float) $value * 100, 1) . '%' : null;
        $details = array_filter([
            'precision ' . $percent($training['precision_mask'] ?? null),
            'recall ' . $percent($training['recall_mask'] ?? null),
            'mAP50-95 ' . $percent($training['map50_95_mask'] ?? null),
        ], fn ($part) => ! str_ends_with($part, ' '));
        $setup = array_filter([
            $training['base_model'] ?? null,
            isset($training['epochs']) ? $training['epochs'] . ' epochs' : null,
            isset($training['imgsz']) ? $training['imgsz'] . ' px' : null,
        ]);

        $description = 'YOLO genus model'
            . ($classes !== [] ? ' (' . implode(', ', $classes) . ')' : '')
            . (! empty($training['dataset']) ? ', trained on Roboflow dataset ' . $training['dataset'] : '')
            . ($setup !== [] ? ' [' . implode(', ', $setup) . ']' : '')
            . '.'
            . ($details !== [] ? ' Validation masks: ' . implode(', ', $details) . '.' : '');

        $trainedAt = null;
        if (! empty($training['trained_at'])) {
            try {
                $trainedAt = \Illuminate\Support\Carbon::parse((string) $training['trained_at']);
            } catch (\Throwable) {
                $trainedAt = null;
            }
        }

        return array_filter([
            'accuracy' => is_numeric($training['map50_mask'] ?? null) ? round((float) $training['map50_mask'], 4) : null,
            'accuracy_metric' => is_numeric($training['map50_mask'] ?? null) ? self::YOLO_CHECKPOINT_METRIC : null,
            'training_date' => $trainedAt,
            'description' => $description,
        ], fn ($value) => $value !== null);
    }

    /**
     * Optional performance figures an admin enters from training: accuracy as a percentage (stored 0–1),
     * the metric it was measured with, and the number of training samples.
     *
     * @return array<string, array<int, string>>
     */
    public static function performanceRules(): array
    {
        return [
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'accuracy_metric' => ['nullable', 'string', 'max:40', 'required_with:accuracy'],
            'dataset_size' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ];
    }

    /** @return array<string, string> */
    public static function performanceMessages(): array
    {
        return [
            'accuracy.numeric' => 'Accuracy must be a number between 0 and 100.',
            'accuracy.min' => 'Accuracy must be between 0 and 100.',
            'accuracy.max' => 'Accuracy must be between 0 and 100 (enter it as a percentage, e.g. 92.4).',
            'accuracy_metric.required_with' => 'Say what the accuracy measures (e.g. IoU or mAP50) so models can be compared fairly.',
            'dataset_size.integer' => 'Dataset size must be a whole number of samples.',
            'dataset_size.min' => 'Dataset size must be at least 1 sample.',
        ];
    }

    /** Turns validated form input (accuracy in %) into the stored attributes. */
    public static function performanceAttributes(array $validated): array
    {
        $accuracy = $validated['accuracy'] ?? null;
        $metric = trim((string) ($validated['accuracy_metric'] ?? ''));

        return [
            'accuracy' => $accuracy === null || $accuracy === '' ? null : round((float) $accuracy / 100, 4),
            'accuracy_metric' => $accuracy === null || $accuracy === '' ? null : ($metric ?: null),
            'dataset_size' => isset($validated['dataset_size']) && $validated['dataset_size'] !== '' ? (int) $validated['dataset_size'] : null,
        ];
    }

    /** File types the app can run for each model type (segmentation: U-Net .pth; genus: YOLO .pt). */
    public static function runnableExtensions(?string $modelType): array
    {
        return match ($modelType) {
            'segmentation' => ['pth'],
            'classification' => ['pt'],
            default => [],
        };
    }

    /** Accuracy as a percentage for display, e.g. 92.4, or null when none was entered. */
    public function accuracyPercent(): ?float
    {
        return $this->accuracy === null ? null : round($this->accuracy * 100, 1);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }
}
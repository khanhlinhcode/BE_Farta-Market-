<?php

namespace Tests\Support;

final class ChatNluMetrics
{
    /**
     * @param  array<int, array{expected: string, predicted: string}>  $predictions
     * @return array{accuracy: float, macro_f1: float, per_intent: array<string, array{precision: float, recall: float, f1: float, support: int}>, confusion_matrix: array<string, array<string, int>>}
     */
    public static function intents(array $predictions): array
    {
        $labels = [];
        foreach ($predictions as $prediction) {
            $labels[$prediction['expected']] = true;
            $labels[$prediction['predicted']] = true;
        }
        $labels = array_keys($labels);
        sort($labels);

        $confusion = [];
        foreach ($labels as $expected) {
            foreach ($labels as $predicted) {
                $confusion[$expected][$predicted] = 0;
            }
        }

        $correct = 0;
        foreach ($predictions as $prediction) {
            $confusion[$prediction['expected']][$prediction['predicted']]++;
            $correct += $prediction['expected'] === $prediction['predicted'] ? 1 : 0;
        }

        $perIntent = [];
        foreach ($labels as $label) {
            $truePositive = $confusion[$label][$label];
            $support = array_sum($confusion[$label]);
            $predictedTotal = array_sum(array_map(
                fn (array $row): int => $row[$label],
                $confusion,
            ));
            $precision = $predictedTotal > 0 ? $truePositive / $predictedTotal : 0.0;
            $recall = $support > 0 ? $truePositive / $support : 0.0;
            $f1 = $precision + $recall > 0
                ? (2 * $precision * $recall) / ($precision + $recall)
                : 0.0;
            $perIntent[$label] = [
                'precision' => round($precision, 4),
                'recall' => round($recall, 4),
                'f1' => round($f1, 4),
                'support' => $support,
            ];
        }

        return [
            'accuracy' => $predictions === [] ? 0.0 : round($correct / count($predictions), 4),
            'macro_f1' => $perIntent === [] ? 0.0 : round(
                array_sum(array_column($perIntent, 'f1')) / count($perIntent),
                4,
            ),
            'per_intent' => $perIntent,
            // Rows are expected labels; columns are predicted labels.
            'confusion_matrix' => $confusion,
        ];
    }
}

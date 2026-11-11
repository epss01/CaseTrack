<?php

namespace App\Services;

use Random\Engine\Mt19937;
use Random\Randomizer;

class CaseProfileService
{
    public function __construct(private readonly CaseDeadlineService $deadlines) {}

    /** The caller supplies visible cases with an in-period FIR, timeline and all three party counts. */
    public function report(iterable $cases): array
    {
        $rows = [];
        $excluded = ['Missing docket date' => 0, 'FIR precedes docket' => 0, 'Invalid complexity' => 0];

        foreach ($cases as $case) {
            $days = $this->deadlines->daysToFir($case->timeline);
            if ($days === null) {
                $excluded['Missing docket date']++;
            } elseif ($days < 0) {
                $excluded['FIR precedes docket']++;
            } elseif ($case->complexity_weight < 1 || $case->complexity_weight > 5) {
                $excluded['Invalid complexity']++;
            } else {
                $rows[$case->id] = [
                    'id' => $case->id,
                    'docket' => $case->docket_no,
                    'values' => [(int) $case->complexity_weight,
                        $case->victims_count + $case->respondents_count + $case->complainants_count, $days],
                ];
            }
        }

        ksort($rows, SORT_NUMERIC);
        $rows = array_values($rows);
        $points = array_column($rows, 'values');
        $n = count($rows);
        $means = $n ? $this->means($points) : null;
        $report = ['submitted' => $n + array_sum($excluded), 'eligible' => $n, 'excluded' => $excluded,
            'cohort' => $means, 'profiles' => [], 'reason' => null];

        if ($n < 15) {
            $report['reason'] = 'At least 15 eligible cases are needed. Try a longer FIR submission period.';

            return $report;
        }
        if (count(array_unique($points, SORT_REGULAR)) < 3) {
            $report['reason'] = 'Fewer than three distinct combinations of recorded values are available.';

            return $report;
        }

        foreach ($means as $axis => $mean) {
            $variance = 0.0;
            foreach ($points as $point) {
                $variance += ($point[$axis] - $mean) ** 2;
            }
            $scale = sqrt($variance / $n);
            foreach ($points as $i => $point) {
                $points[$i][$axis] = $scale > 0 ? ($point[$axis] - $mean) / $scale : 0.0;
            }
        }

        $best = null;
        for ($seed = 1; $seed <= 10; $seed++) {
            $fit = $this->fit($points, $seed);
            if ($fit !== null && ($best === null || $fit['error'] < $best['error'])) {
                $best = $fit;
            }
        }
        if ($best === null) {
            $report['reason'] = 'Three stable profiles could not be fitted for this period.';

            return $report;
        }

        $groups = [];
        foreach ($best['labels'] as $i => $label) {
            $groups[$label][] = $rows[$i];
        }
        foreach ($groups as $members) {
            if (count($members) < 3) {
                $report['reason'] = 'The best fit included a profile with fewer than three cases. Try a longer period.';

                return $report;
            }
        }
        foreach ($groups as $members) {
            $report['profiles'][] = ['means' => $this->means(array_column($members, 'values')), 'members' => $members];
        }
        $order = static fn ($group) => [$group['means'][2], $group['means'][1], $group['means'][0], $group['members'][0]['id']];
        usort($report['profiles'], static fn ($a, $b) => $order($a) <=> $order($b));

        return $report;
    }

    private function means(array $points): array
    {
        $means = [];
        for ($axis = 0; $axis < 3; $axis++) {
            $means[] = array_sum(array_column($points, $axis)) / count($points);
        }

        return $means;
    }

    private function distance(array $a, array $b): float
    {
        return ($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2;
    }

    private function fit(array $points, int $seed): ?array
    {
        $random = new Randomizer(new Mt19937($seed));
        $centers = [$points[$random->getInt(0, count($points) - 1)]];
        // K-means++ samples each next center in proportion to squared distance to the nearest center.
        while (count($centers) < 3) {
            $weights = [];
            foreach ($points as $point) {
                $weights[] = min(array_map(fn ($center) => $this->distance($point, $center), $centers));
            }
            $threshold = $random->nextFloat() * array_sum($weights);
            $cumulative = 0.0;
            foreach ($weights as $i => $weight) {
                $cumulative += $weight;
                if ($cumulative > $threshold) {
                    $centers[] = $points[$i];
                    break;
                }
            }
            if ($cumulative <= $threshold) {
                return null;
            }
        }

        $previous = [];
        for ($iteration = 0; $iteration < 100; $iteration++) {
            $groups = $labels = [];
            $error = 0.0;
            foreach ($points as $point) {
                $distances = array_map(fn ($center) => $this->distance($point, $center), $centers);
                $nearest = min($distances);
                $label = array_search($nearest, $distances, true);
                $labels[] = $label;
                $groups[$label][] = $point;
                $error += $nearest;
            }
            if (count($groups) !== 3) {
                return null;
            }
            if ($labels === $previous) {
                return ['labels' => $labels, 'error' => $error];
            }
            $previous = $labels;
            foreach ($groups as $label => $members) {
                $centers[$label] = $this->means($members);
            }
        }

        return null;
    }
}


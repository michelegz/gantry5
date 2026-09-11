<?php

namespace Gantry\Tests\PHP83\Platform;

use Gantry\Joomla\Content\ContentRanker;
use Gantry\Tests\PHP83\MockableTest;

/**
 * Test generic ranking merge and sort helpers.
 */
class ContentRankerTest extends MockableTest
{
    public function testMergeKeepsHighestScore()
    {
        $merged = ContentRanker::mergeScoreMaps([
            [1 => 10, 2 => 5],
            [1 => 7, 2 => 9, 3 => 1]
        ]);

        $this->assertSame([1 => 10, 2 => 9, 3 => 1], $merged);
    }

    public function testMergeIgnoresInvalidEntries()
    {
        $merged = ContentRanker::mergeScoreMaps([
            [0 => 99, -3 => 99, 'abc' => 99, 4 => 'NaN', 5 => null, 6 => '42'],
            'not-an-array',
            [7 => 2.5]
        ]);

        $this->assertSame([6 => 42, 7 => 2.5], $merged);
    }

    public function testSortIsStableDescWithUnscoredTail()
    {
        $ranked = ContentRanker::sortRanked([1, 2, 3, 4, 5], [2 => 10, 4 => 10, 1 => 3]);

        // Ties keep original order, unscored keep original order at the end.
        $this->assertSame([2, 4, 1, 3, 5], $ranked);
    }

    public function testSortDedupesAndNormalizesIds()
    {
        $ranked = ContentRanker::sortRanked(['3', 1, 3, 0, -2, 2], [2 => 5]);

        $this->assertSame([2, 3, 1], $ranked);
    }

    public function testSortWithEmptyInputs()
    {
        $this->assertSame([], ContentRanker::sortRanked([], [1 => 5]));
        $this->assertSame([1, 2], ContentRanker::sortRanked([1, 2], []));
        $this->assertSame([], ContentRanker::mergeScoreMaps([]));
    }

    public function testFloatScoresSortNumericallyNotLexicographically()
    {
        // String comparison would order "11.25" < "2.0" < "9.5".
        $ranked = ContentRanker::sortRanked([1, 2, 3], [1 => 9.5, 2 => 11.25, 3 => 2.0]);

        $this->assertSame([2, 1, 3], $ranked);
    }

    public function testNumericStringScoresSortNumerically()
    {
        // String comparison would order "11" < "12" < "2" < "9.5".
        $ranked = ContentRanker::sortRanked([1, 2, 3, 4], [1 => '9.5', 2 => '11', 3 => '12', 4 => '2']);

        $this->assertSame([3, 2, 1, 4], $ranked);
    }

    public function testNanScoresAreIgnored()
    {
        // NaN would poison the sort, the ID ends up unscored instead.
        $ranked = ContentRanker::sortRanked([1, 2, 3], [1 => NAN, 2 => 5.0, 3 => 1.0]);

        $this->assertSame([2, 3, 1], $ranked);
    }

    public function testTiesKeepCandidateOrderNeverIdOrder()
    {
        // Descending IDs with equal scores must stay in candidate order:
        // the ID itself is never an implicit tie-breaker.
        $ranked = ContentRanker::sortRanked([11, 2, 7], [11 => 5.0, 2 => 5.0]);

        $this->assertSame([11, 2, 7], $ranked);
    }

    public function testAllTiedKeepsCandidateOrder()
    {
        $ranked = ContentRanker::sortRanked([3, 1, 2], [3 => 1.5, 1 => 1.5, 2 => 1.5]);

        $this->assertSame([3, 1, 2], $ranked);
    }

    public function testStringKeysAreCoercedToInt()
    {
        $ranked = ContentRanker::sortRanked([8, 9], ['08' => 3, 9 => 1]);

        $this->assertSame([8, 9], $ranked);
    }
}

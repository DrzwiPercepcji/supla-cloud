<?php
namespace SuplaBundle\Tests\Model\SchedulePlanner;

use SuplaBundle\Model\SchedulePlanners\SunriseSunsetSchedulePlanner;

class SunriseSunsetSchedulePlannerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider calculatingNextRunDateProvider
     */
    public function testCalculatingNextRunDate($startDate, $cronExpression, $expectedNextRunDate, $timezone = 'Europe/Warsaw')
    {
        $schedulePlanner = new SunriseSunsetSchedulePlanner();
        $schedule = new ScheduleWithTimezone($cronExpression, $timezone);
        $format = 'Y-m-d H:i';
        $startDate = \DateTime::createFromFormat($format, $startDate);
        $this->assertTrue($schedulePlanner->canCalculateFor($schedule));
        $nextRunDate = $schedulePlanner->calculateNextRunDate($schedule, $startDate);
        $this->assertEquals($expectedNextRunDate, $nextRunDate->format($format));
    }

    public function calculatingNextRunDateProvider()
    {
        return [
            ['2017-01-01 00:00', 'SR0 * * * *', '2017-01-01 07:45'], // sunrise: 7:46, http://suncalc.net/#/52.2297,21.0122,11/2017.01.01/13:11
            ['2017-01-01 00:00', 'SS0 * * * *', '2017-01-01 15:35'], // sunset: 15:34
            ['2017-06-24 00:00', 'SR0 * * * *', '2017-06-24 04:15'], // sunrise: 4:15, http://suncalc.net/#/52.2297,21.0122,11/2017.06.24/13:11
            ['2017-06-24 00:00', 'SS0 * * * *', '2017-06-24 21:00'], // sunrise: 21:01
            ['2017-06-29 00:00', 'SR0 * * * *', '2017-06-29 04:20'], // sunrise: 4:18, http://suncalc.net/#/52.2297,21.0122,11/2017.06.29/13:11
            ['2017-06-29 00:00', 'SS0 * * * *', '2017-06-29 21:00'], // sunset: 21:02
            ['2017-06-29 00:00', 'SS0 * * * *', '2017-06-29 16:55', 'Australia/Sydney'], // sunset: 21:02
            ['2017-06-24 04:14', 'SR0 * * * *', '2017-06-24 04:15'],
            ['2017-06-24 04:14', 'SR5 * * * *', '2017-06-24 04:20'],
            ['2017-06-24 04:14', 'SR15 * * * *', '2017-06-24 04:30'],
            ['2017-06-24 04:14', 'SR-5 * * * *', '2017-06-25 04:10'],
            ['2017-06-24 04:00', 'SR-5 * * * *', '2017-06-24 04:10'],
            ['2017-06-24 01:00', 'SR-15 * * * *', '2017-06-24 04:00'],
            ['2017-06-24 01:00', 'SR-150 * * * *', '2017-06-24 01:45'],
            ['2017-06-24 01:00', 'SR150 * * * *', '2017-06-24 06:45'],
            ['2017-06-24 04:15', 'SR0 * * * *', '2017-06-25 04:15'],
            ['2017-06-24 06:15', 'SR0 * * * *', '2017-06-25 04:15'],
            ['2017-02-18 13:51', 'SR0 * * * *', '2017-02-19 06:40'],
            ['2017-02-18 13:51', 'SS0 * * * *', '2017-02-18 16:55'],
            ['2017-02-18 13:51', 'SS0 * * * 1,2,3,4,5', '2017-02-20 17:00'], // only chosen weekdays
            ['2017-02-18 13:51', 'SR0 * * * 6', '2017-02-25 06:30'],
            ['2017-02-18 13:51', 'SR0 * * * 6', '2017-02-25 06:30'],
            ['2017-02-18 13:51', 'SR0 * 14 3 *', '2017-03-14 05:50'],
        ];
    }
}

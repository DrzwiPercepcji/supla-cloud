<?php
/*
 Copyright (C) AC SOFTWARE SP. Z O.O.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace SuplaBundle\Controller\Api;

use Assert\Assert;
use Assert\Assertion;
use DateTime;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use SuplaBundle\Entity\IODeviceChannel;
use SuplaBundle\Entity\IODeviceChannelGroup;
use SuplaBundle\Entity\Scene;
use SuplaBundle\Entity\Schedule;
use SuplaBundle\Entity\ScheduledExecution;
use SuplaBundle\EventListener\UnavailableInMaintenance;
use SuplaBundle\Model\ApiVersions;
use SuplaBundle\Model\Schedule\ScheduleManager;
use SuplaBundle\Repository\ActionableSubjectRepository;
use SuplaBundle\Repository\ScheduleListQuery;
use SuplaBundle\Repository\ScheduleRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ScheduleController extends RestController {
    /** @var ScheduleRepository */
    private $scheduleRepository;
    /** @var ScheduleManager */
    private $scheduleManager;
    /** @var ActionableSubjectRepository */
    private $subjectRepository;

    public function __construct(
        ScheduleRepository $scheduleRepository,
        ActionableSubjectRepository $subjectRepository,
        ScheduleManager $scheduleManager
    ) {
        $this->scheduleRepository = $scheduleRepository;
        $this->scheduleManager = $scheduleManager;
        $this->subjectRepository = $subjectRepository;
    }

    protected function getDefaultAllowedSerializationGroups(Request $request): array {
        if (ApiVersions::V2_3()->isRequestedEqualOrGreaterThan($request)) {
            return ['subject', 'closestExecutions', 'subject' => 'schedule.subject'];
        } else {
            return ['channel', 'iodevice', 'location', 'closestExecutions'];
        }
    }

    /** @Security("has_role('ROLE_SCHEDULES_R')") */
    public function getSchedulesAction(Request $request) {
        return $this->returnSchedules(ScheduleListQuery::create()->filterByUser($this->getUser()), $request);
    }

    /**
     * @Security("channel.belongsToUser(user) and has_role('ROLE_CHANNELS_R')")
     */
    public function getChannelSchedulesAction(IODeviceChannel $channel, Request $request) {
        return $this->returnSchedules(ScheduleListQuery::create()->filterByChannel($channel), $request);
    }

    /**
     * @Security("channelGroup.belongsToUser(user) and has_role('ROLE_CHANNELGROUPS_R')")
     * @Rest\Get("/channel-groups/{channelGroup}/schedules")
     */
    public function getChannelGroupSchedulesAction(IODeviceChannelGroup $channelGroup, Request $request) {
        return $this->returnSchedules(ScheduleListQuery::create()->filterByChannelGroup($channelGroup), $request);
    }

    /**
     * @Security("scene.belongsToUser(user) and has_role('ROLE_SCENES_R')")
     * @Rest\Get("/scenes/{scene}/schedules")
     */
    public function getSceneSchedulesAction(Scene $scene, Request $request) {
        return $this->returnSchedules(ScheduleListQuery::create()->filterByScene($scene), $request);
    }

    private function returnSchedules(ScheduleListQuery $query, Request $request) {
        if (count($sort = explode('|', $request->get('sort', ''))) == 2) {
            $query->orderBy($sort[0], $sort[1]);
        }
        $schedules = $this->scheduleRepository->findByQuery($query);
        $view = $this->serializedView($schedules, $request);
        $view->setHeader('SUPLA-Total-Schedules', $this->getUser()->getSchedules()->count());
        return $view;
    }

    /**
     * @Security("schedule.belongsToUser(user) and has_role('ROLE_SCHEDULES_R')")
     */
    public function getScheduleAction(Request $request, Schedule $schedule) {
        return $this->serializedView($schedule, $request, ['subject.relationsCount']);
    }

    /**
     * @Security("has_role('ROLE_SCHEDULES_RW')")
     * @UnavailableInMaintenance
     */
    public function postScheduleAction(Request $request) {
        Assertion::false($this->getCurrentUser()->isLimitScheduleExceeded(), 'Schedule limit has been exceeded'); // i18n
        $data = $request->request->all();
        if (!ApiVersions::V2_3()->isRequestedEqualOrGreaterThan($request)) {
            $data['subjectId'] = $data['channelId'] ?? null;
            $data['subjectType'] = 'channel';
        }
        $schedule = $this->fillSchedule(new Schedule($this->getCurrentUser()), $data);
        $this->getDoctrine()->getManager()->persist($schedule);
        $this->getDoctrine()->getManager()->flush();
        if ($schedule->isSubjectEnabled()) {
            $this->scheduleManager->enable($schedule);
        }
        return $this->serializedView($schedule, $request, ['subject.relationsCount'], Response::HTTP_CREATED);
    }

    /**
     * @Security("schedule.belongsToUser(user) and has_role('ROLE_SCHEDULES_RW')")
     * @UnavailableInMaintenance
     */
    public function putScheduleAction(Request $request, Schedule $schedule) {
        $data = $request->request->all();
        if (!ApiVersions::V2_3()->isRequestedEqualOrGreaterThan($request)) {
            $data['subjectId'] = $data['channelId'] ?? null;
            $data['subjectType'] = 'channel';
        }
        $this->fillSchedule($schedule, $data);
        return $this->getDoctrine()->getManager()->transactional(function ($em) use ($schedule, $request, $data) {
            $this->scheduleManager->deleteScheduledExecutions($schedule);
            $em->persist($schedule);
            if (!$schedule->getEnabled() && ($request->get('enable') || ($data['enabled'] ?? false))) {
                $this->scheduleManager->enable($schedule);
            } elseif ($schedule->getEnabled() && (!($data['enabled'] ?? true) || !$schedule->isSubjectEnabled())) {
                $this->scheduleManager->disable($schedule);
            }
            if ($schedule->getEnabled()) {
                $this->scheduleManager->generateScheduledExecutions($schedule);
            }
            return $this->view($schedule, Response::HTTP_OK);
        });
    }

    /** @return Schedule */
    private function fillSchedule(Schedule $schedule, array $data) {
        Assert::that($data)
            ->notEmptyKey('subjectId')
            ->notEmptyKey('subjectType')
            ->notEmptyKey('mode');
        $subject = $this->subjectRepository->findForUser($this->getUser(), $data['subjectType'], $data['subjectId']);
        $data['subject'] = $subject;
        $schedule->fill($data);
        $this->scheduleManager->validateSchedule($schedule);
        return $schedule;
    }

    /**
     * @Security("has_role('ROLE_SCHEDULES_RW')")
     * @UnavailableInMaintenance
     */
    public function patchSchedulesAction(Request $request) {
        $data = $request->request->all();
        $this->getDoctrine()->getManager()->transactional(function () use ($data) {
            if (isset($data['enable'])) {
                foreach ($this->getCurrentUser()->getSchedules() as $schedule) {
                    if (in_array($schedule->getId(), $data['enable']) && !$schedule->getEnabled()) {
                        $this->scheduleManager->enable($schedule);
                    }
                }
            }
        });
        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Security("schedule.belongsToUser(user) and has_role('ROLE_SCHEDULES_RW')")
     * @UnavailableInMaintenance
     */
    public function deleteScheduleAction(Schedule $schedule) {
        $this->scheduleManager->delete($schedule);
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Rest\Post("/schedules/next-run-dates")
     * @Security("has_role('ROLE_SCHEDULES_R')")
     * @deprecated
     */
    public function getNextRunDatesAction(Request $request) {
        // TODO uncomment in v2.4
        // Assertion::false(ApiVersions::V2_4()->isRequestedEqualOrGreaterThan($request), 'Endpoint not available in v2.4.');
        $data = $request->request->all();
        $temporarySchedule = new Schedule($this->getCurrentUser(), $data);
        $nextRunDates = $this->scheduleManager->getNextScheduleExecutions($temporarySchedule, '+7days', 3);
        return $this->view(array_map(function (ScheduledExecution $execution) {
            return $execution->getPlannedTimestamp()->format(DateTime::ATOM);
        }, $nextRunDates), Response::HTTP_OK);
    }

    /**
     * @Rest\Post("/schedules/next-schedule-executions")
     * @Security("has_role('ROLE_SCHEDULES_R')")
     * @deprecated
     */
    public function getNextScheduleExecutionsAction(Request $request) {
        $data = $request->request->all();
        $temporarySchedule = new Schedule($this->getCurrentUser(), $data);
        $scheduleExecutions = $this->scheduleManager->getNextScheduleExecutions($temporarySchedule, '+7days', 3);
        return $this->view($scheduleExecutions, Response::HTTP_OK);
    }
}

<?php
/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2018 PublishPress
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace PublishPress\AsyncNotifications\Queue;

use PublishPress\Notifications\Traits\Dependency_Injector;
use PublishPress\Debug\DebuggerTrait;

/**
 * Class DBAdapter
 *
 * @package PublishPress\AsyncNotifications\Queue
 */
class WPCron implements QueueInterface
{
    use Dependency_Injector, DebuggerTrait;

    /**
     * Enqueue the notification for async processing.
     *
     * @param $workflowPost
     * @param $actionArgs
     * @param $receivers
     * @param $content
     * @param $channel
     *
     * @throws \Exception
     */
    public function enqueueNotification($workflowPost, $actionArgs, $receivers, $content, $channel)
    {
        if (!is_array($receivers))
        {
            $receivers = [$receivers];
        }

        if (!empty($receivers))
        {
            $baseData = [
                // workflow_post_id
                $workflowPost->ID,
                // action
                $actionArgs['action'],
                // post_id
                $actionArgs['post']->ID,
                // content
                base64_encode(maybe_serialize($content)),
                // old_status
                isset($actionArgs['old_status']) ? $actionArgs['old_status'] : null,
                // new_status
                isset($actionArgs['new_status']) ? $actionArgs['new_status'] : null,
                // channel
                $channel,
            ];

            // Create one notification for each receiver in the queue
            foreach ($receivers as $receiver)
            {
                // Base data
                $data = $baseData;

                // Receiver
                $data[] = $receiver;

                $this->scheduleEvent($data);
            }
        }
    }

    /**
     * Schedule the notification event.
     *
     * @param $data
     * @throws \Exception
     */
    protected function scheduleEvent($data)
    {
        /*
         * Debug.
         */
        $this->log(
            'scheduleEvent: enqueuing notification (%s, %s, %s, %s, %s, %s)',
            [
                $data[0],
                $data[1],
                $data[2],
                $data[4],
                $data[5],
                $data[6],
            ]
        );

        wp_schedule_single_event(
            time(),
            'publishpress_cron_notify',
            $data
        );
    }
}

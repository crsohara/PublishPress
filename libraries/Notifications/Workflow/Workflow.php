<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow;

use
use PublishPress\Debug\DebuggerTrait;

class Workflow
{
    use Dependency_Injector, DebuggerTrait;

    /**
     * The post of this workflow.
     *
     * @var WP_Post
     */
    protected $workflow_post;

    /**
     * An array with arguments set by the action
     *
     * @var array
     */
    protected $action_args;

    /**
     * The constructor
     *
     * @param WP_Post $workflow_post
     */
    public function __construct($workflow_post)
    {
        $this->workflow_post = $workflow_post;
    }

    /**
     * Runs this workflow without applying any filter. We assume it was
     * already filtered in the query.
     *
     * @param array $args
     *
     * @throws \Exception
     */
    public function run($args)
    {
        /*
         * Debug.
         */
        $this->log(
            'run (%s)',
            [
                $this->workflow_post->ID,
            ]
        );


        $this->action_args = $args;

        // Who will receive the notification?
        $receivers = $this->get_receivers();

        // If we don't have receivers, abort the workflow.
        if (empty($receivers))
        {
            /*
             * Debug.
             */
            $this->log(
                'run: no receivers found (%s)',
                [
                    $this->workflow_post->ID,
                ]
            );

            return;
        }

        /*
         * What will the notification says?
         *
         * TODO: Allow custom message for each user, so we can mention him, or other user related data. Add another shortcode replacements?
         */
        $content = $this->get_content();

        // Run the action to each receiver.
        foreach ($receivers as $channel => $channel_receivers)
        {
            foreach ($channel_receivers as $receiver)
            {
                /**
                 * Filters the action to be executed. By default it will trigger the notification.
                 * But it can be changed to do another action. This allows to change the flow and
                 * catch the params to cache or queue for async notifications.
                 *
                 * @param string   $action
                 * @param Workflow $workflow
                 * @param string   $channel
                 */
                $action = apply_filters('publishpress_notif_workflow_run_action', 'publishpress_notif_send_notification_' . $channel, $this, $channel);

                /*
                 * Debug.
                 */
                $this->log(
                    'run: doing action ' . $action . ' (%s, %s, %s)',
                    [
                        $action,
                        $this->workflow_post->ID,
                        $channel,
                    ]
                );

                /**
                 * Triggers the notification. This can be caught by notification channels.
                 * But can be intercepted by other plugins (cache, async, etc) to change the
                 * workflow.
                 *
                 * @param WP_Post $workflow_post
                 * @param array   $action_args
                 * @param array   $receiver
                 * @param array   $content
                 * @param string  $channel
                 */
                do_action($action, $this->workflow_post, $this->action_args, $receiver, $content, $channel);
            }
        }
    }

    /**
     * Returns a list of receivers ids for this workflow
     *
     * @return array
     */
    protected function get_receivers()
    {
        $filtered_receivers = [];

        /**
         * Filters the list of receivers for the notification workflow.
         *
         * @param WP_Post $workflow
         * @param array   $args
         */
        $receivers = apply_filters('publishpress_notif_run_workflow_receivers', [], $this->workflow_post, $this->action_args);

        if (!empty($receivers))
        {
            // Remove duplicate receivers
            $receivers = array_unique($receivers, SORT_NUMERIC);

            // Classify receivers per channel, ignoring who has muted the channel.
            foreach ($receivers as $index => $receiver)
            {
                // Is an user (identified by the id)?
                if (is_numeric($receiver))
                {
                    $channel = get_user_meta($receiver, 'psppno_workflow_channel_' . $this->workflow_post->ID, true);

                    // If channel is empty, we set a default channel.
                    if (empty($channel)) {
                        $channel = apply_filters('psppno_default_channel', 'email');
                    }

                    // If the channel is "mute", we ignore this receiver.
                    if ('mute' === $channel)
                    {
                        continue;
                    }

                    // Make sure the array for the channel is initialized.
                    if (!isset($filtered_receivers[$channel]))
                    {
                        $filtered_receivers[$channel] = [];
                    }

                    // Add to the channel's list.
                    $filtered_receivers[$channel][] = $receiver;
                } else
                {
                    // Check if it is an explicit email address.
                    if (preg_match('/^email:/', $receiver))
                    {
                        if (!isset($filtered_receivers['email']))
                        {
                            $filtered_receivers['email'] = [];
                        }

                        // Add to the email channel, without the "email:" prefix.
                        $filtered_receivers['email'][] = str_replace('email:', '', $receiver);
                    }
                }
            }
        }

        return $filtered_receivers;
    }

    /**
     * Returns the content for the notification, as an associative array with
     * the following keys:
     *     - subject
     *     - body
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function get_content()
    {
        $shortcodes = $this->get_service('shortcodes');

        $shortcodes->register($this->workflow_post, $this->action_args);

        $content = ['subject' => '', 'body' => ''];
        /**
         * Filters the content for the notification workflow.
         *
         * @param WP_Post $workflow
         * @param array   $args
         */
        $content = apply_filters('publishpress_notif_run_workflow_content', $content, $this->workflow_post, $this->action_args);

        if (!array_key_exists('subject', $content))
        {
            $content['subject'] = '';
        }

        if (!array_key_exists('body', $content))
        {
            $content['body'] = '';
        }

        // Replace placeholders in the subject and body
        $content['subject'] = $this->filter_shortcodes($content['subject']);
        $content['body']    = $this->filter_shortcodes($content['body']);

        $shortcodes->unregister();

        return $content;
    }

    /**
     * @param $text
     *
     * @return string
     */
    protected function filter_shortcodes($text)
    {
        return do_shortcode($text);
    }
}

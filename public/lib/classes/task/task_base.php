<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Abstract class for common properties of scheduled_task and adhoc_task.
 *
 * @package    core
 * @category   task
 * @copyright  2013 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\task;

use core_component;
use core_plugin_manager;
use core\check\result;

/**
 * Abstract class for common properties of scheduled_task and adhoc_task.
 *
 * @copyright  2013 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class task_base {

    /** @var \core\lock\lock $lock - The lock controlling this task. */
    private $lock = null;

    /** @var \core\lock\lock $cronlock - The lock controlling the entire cron process. */
    private $cronlock = null;

    /** @var string $component - The component this task belongs to. */
    private $component = '';

    /** @var int $faildelay - Exponentially increasing fail delay */
    private $faildelay = 0;

    /** @var int $nextruntime - When this task is due to run next */
    private $nextruntime = 0;

    /** @var int $timestarted - When this task was started */
    private $timestarted = null;

    /** @var string $hostname - Hostname where this task was started and PHP process ID */
    private $hostname = null;

    /** @var int $pid - PHP process ID that is running the task */
    private $pid = null;

    /**
     * Get a descriptive name for the task (shown to admins)
     *
     * @return string
     */
    abstract public function get_name();

    /**
     * Set the current lock for this task.
     * @param \core\lock\lock $lock
     */
    public function set_lock(\core\lock\lock $lock) {
        $this->lock = $lock;
    }

    /**
     * Set the current lock for the entire cron process.
     * @param \core\lock\lock $lock
     */
    public function set_cron_lock(\core\lock\lock $lock) {
        $this->cronlock = $lock;
    }

    /**
     * Get the current lock for this task.
     * @return \core\lock\lock
     */
    public function get_lock() {
        return $this->lock;
    }

    /**
     * Get the next run time for this task.
     * @return int timestamp
     */
    public function get_next_run_time() {
        return $this->nextruntime;
    }

    /**
     * Set the next run time for this task.
     * @param int $nextruntime
     */
    public function set_next_run_time($nextruntime) {
        $this->nextruntime = $nextruntime;
    }

    /**
     * Get the current lock for the entire cron.
     * @return \core\lock\lock
     */
    public function get_cron_lock() {
        return $this->cronlock;
    }

    /**
     * @deprecated since Moodle 4.4 See MDL-67667
     */
    #[\core\attribute\deprecated(
        replacement: null,
        since: '4.4',
        mdl: 'MDL-67667',
        reason: 'Blocking tasks are no longer supported',
        final: true,
    )]
    public function set_blocking() {
        \core\deprecation::emit_deprecation([$this, __FUNCTION__]);
    }

    /**
     * @deprecated since Moodle 4.4 See MDL-67667
     */
    #[\core\attribute\deprecated(
        replacement: null,
        since: '4.4',
        mdl: 'MDL-67667',
        reason: 'Blocking tasks are no longer supported',
        final: true,
    )]
    public function is_blocking() {
        \core\deprecation::emit_deprecation([$this, __FUNCTION__]);
    }

    /**
     * Setter for $component.
     * @param string $component
     */
    public function set_component($component) {
        $this->component = $component;
    }

    /**
     * Getter for $component.
     * @return string
     */
    public function get_component() {
        if (empty($this->component)) {
            // The component should be the root of the class namespace.
            $classname = get_class($this);
            $parts = explode('\\', $classname);

            if (count($parts) === 1) {
                $component = substr($classname, 0, strpos($classname, '_task'));
            } else {
                [$component] = $parts;
            }

            // Load component information from plugin manager.
            if ($component !== 'core' && strpos($component, 'core_') !== 0) {
                $plugininfo = \core_plugin_manager::instance()->get_plugin_info($component);
                if ($plugininfo && $plugininfo->component) {
                    $this->set_component($plugininfo->component);
                } else {
                    debugging("Component not set and the class namespace does not match a valid component ({$component}).");
                }
            }
        }

        return $this->component;
    }

    /**
     * Setter for $faildelay.
     * @param int $faildelay
     */
    public function set_fail_delay($faildelay) {
        $this->faildelay = $faildelay;
    }

    /**
     * Getter for $faildelay.
     * @return int
     */
    public function get_fail_delay() {
        return $this->faildelay;
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    abstract public function execute();

    /**
     * Setter for $timestarted.
     * @param int $timestarted
     */
    public function set_timestarted($timestarted = null) {
        $this->timestarted = $timestarted;
    }

    /**
     * Getter for $timestarted.
     * @return int
     */
    public function get_timestarted() {
        return $this->timestarted;
    }

    /**
     * Setter for $hostname.
     * @param string $hostname
     */
    public function set_hostname($hostname = null) {
        $this->hostname = $hostname;
    }

    /**
     * Getter for $hostname.
     * @return string
     */
    public function get_hostname() {
        return $this->hostname;
    }

    /**
     * Setter for $pid.
     * @param int $pid
     */
    public function set_pid($pid = null) {
        $this->pid = $pid;
    }

    /**
     * Getter for $pid.
     * @return int
     */
    public function get_pid() {
        return $this->pid;
    }

    /**
     * Informs whether the task's component is enabled.
     * @return bool true when enabled. false otherwise.
     */
    public function is_component_enabled(): bool {
        $component = $this->get_component();

        // An entire core component type cannot be explicitly disabled.
        [$componenttype] = core_component::normalize_component($component);
        if ($componenttype === 'core') {
            return true;
        } else {
            $plugininfo = core_plugin_manager::instance()->get_plugin_info($component);
            return $plugininfo && ($plugininfo->is_enabled() !== false);
        }
    }

    /**
     * Returns task runtime
     * @return int
     */
    public function get_runtime() {
        return time() - $this->timestarted;
    }

    /**
     * Returns if the task has been running for too long
     * @return result
     */
    public function get_runtime_result() {
        global $CFG;
        $runtime = $this->get_runtime();
        $runtimeerror = $CFG->taskruntimeerror;
        $runtimewarn = $CFG->taskruntimewarn;

        $status = result::OK;
        $details = '';

        if ($runtime > $runtimewarn) {
            $status = result::WARNING;
            $details = get_string('slowtask', 'tool_task', format_time($runtimewarn));
        }

        if ($runtime > $runtimeerror) {
            $status = result::ERROR;
            $details = get_string('slowtask', 'tool_task', format_time($runtimeerror));
        }

        // This result is aggregated with other running tasks checks before display.
        return new result($status, '', $details);
    }

}

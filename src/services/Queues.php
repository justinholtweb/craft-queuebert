<?php

namespace justinholtweb\queuebert\services;

use Craft;
use craft\i18n\Translation;
use craft\queue\QueueInterface;
use justinholtweb\queuebert\Plugin;
use yii\base\Component;

/**
 * Discovers every queue in the application (Craft's default queue plus any
 * custom queue runners registered as application components) and aggregates
 * their live activity into a shape the control-panel bar can render.
 */
class Queues extends Component
{
    /**
     * Returns every application component that implements QueueInterface,
     * keyed by its component id (handle).
     *
     * @return array<string, QueueInterface>
     */
    public function getQueues(): array
    {
        $app = Craft::$app;
        $queues = [];

        // The primary queue is always id "queue" — pull it directly so we never
        // miss it, even if the component is defined lazily.
        try {
            $primary = $app->getQueue();
            if ($primary instanceof QueueInterface) {
                $queues['queue'] = $primary;
            }
        } catch (\Throwable $e) {
            Craft::warning('Queuebert could not load the primary queue: ' . $e->getMessage(), 'queuebert');
        }

        // Scan the remaining component definitions for additional queue runners.
        // Custom queues are conventionally registered as app components in
        // config/app.php (e.g. "queue2", "importQueue"). Pass true so we get
        // every declared definition, not just components already instantiated
        // this request.
        foreach ($app->getComponents(true) as $id => $definition) {
            if (isset($queues[$id])) {
                continue;
            }

            if (!$this->definitionIsQueue($id, $definition)) {
                continue;
            }

            try {
                $component = $app->get($id, false);
                if ($component instanceof QueueInterface) {
                    $queues[$id] = $component;
                }
            } catch (\Throwable $e) {
                Craft::warning("Queuebert could not load queue \"$id\": " . $e->getMessage(), 'queuebert');
            }
        }

        return $queues;
    }

    /**
     * Builds the live activity summary across all queues.
     *
     * @return array{
     *     active: bool,
     *     totalRunning: int,
     *     totalWaiting: int,
     *     totalFailed: int,
     *     current: array{description: string, progress: int, progressLabel: string, queue: string}|null,
     *     queues: array<int, array{handle: string, name: string, running: int, waiting: int, failed: int}>
     * }
     */
    public function getActivity(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $limit = max(1, $settings->inspectLimit);

        // Read + normalize each queue's job info (translating any lazy strings),
        // then hand the framework-agnostic data to QueueActivity to aggregate.
        $queues = [];
        foreach ($this->getQueues() as $handle => $queue) {
            try {
                $jobs = $queue->getJobInfo($limit);
            } catch (\Throwable $e) {
                Craft::warning("Queuebert could not read job info for \"$handle\": " . $e->getMessage(), 'queuebert');
                continue;
            }

            $queues[] = [
                'handle' => $handle,
                'name' => $this->queueName($handle),
                'jobs' => array_map(fn(array $job): array => [
                    'status' => (int)($job['status'] ?? 0),
                    'description' => $this->translate($job['description'] ?? null),
                    'progress' => (int)($job['progress'] ?? 0),
                    'progressLabel' => $this->translate($job['progressLabel'] ?? null),
                ], $jobs),
            ];
        }

        return QueueActivity::summarize(
            $queues,
            $settings->countWaiting,
            $settings->showFailed,
            Craft::t('queuebert', 'Running job'),
        );
    }

    /**
     * Best-effort check of whether a component definition resolves to a queue,
     * without instantiating it.
     */
    private function definitionIsQueue(string $id, mixed $definition): bool
    {
        // Already-instantiated component.
        if ($definition instanceof QueueInterface) {
            return true;
        }

        // A conventional id is a strong hint even when the class can't be
        // resolved statically (e.g. Closure factories).
        $looksLikeQueue = stripos($id, 'queue') !== false;

        $class = $this->resolveClass($definition);
        if ($class !== null && is_a($class, QueueInterface::class, true)) {
            return true;
        }

        // Fall back to the id hint only when we couldn't resolve a class at all;
        // getQueues() still verifies the instance before using it.
        return $class === null && $looksLikeQueue;
    }

    /**
     * Attempts to statically resolve a class name from a component definition.
     */
    private function resolveClass(mixed $definition): ?string
    {
        if (is_string($definition)) {
            return $definition;
        }

        if (is_object($definition)) {
            return $definition::class;
        }

        if (is_array($definition)) {
            $class = $definition['class'] ?? $definition['__class'] ?? null;
            return is_string($class) ? $class : null;
        }

        return null;
    }

    /**
     * Resolves a friendly display name for a queue handle.
     */
    private function queueName(string $handle): string
    {
        if ($handle === 'queue') {
            return Craft::t('queuebert', 'Default queue');
        }

        return $handle;
    }

    /**
     * Lazily-translates a value that came back from getJobInfo(), which may be
     * a Translation-encoded string.
     */
    private function translate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Translation::translate($value);
    }
}

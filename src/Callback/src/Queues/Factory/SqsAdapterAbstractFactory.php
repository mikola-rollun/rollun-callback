<?php
/**
 * @copyright Copyright © 2014 Rollun LC (http://rollun.com/)
 * @license LICENSE.md New BSD License
 */

declare(strict_types = 1);

namespace rollun\callback\Queues\Factory;

use Aws\Sqs\SqsClient;
use Interop\Container\ContainerInterface;
use InvalidArgumentException;
use ReputationVIP\QueueClient\PriorityHandler\StandardPriorityHandler;
use rollun\callback\Queues\Adapter\SqsAdapter;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;

/**
 * Create instance of SQSAdapter
 *
 * Config example:
 *
 * <code>
 *  [
 *      SqsAdapterAbstractFactory::class => [
 *          'requestedServiceName1' => [
 *              'priorityHandler' => 'priorityHandlerServiceName',
 *              'sqsClientConfig' => [
 *                  // ...
 *              ],
 *              'sqsAttributes' => [
 *                  'VisibilityTimeout' => 10,
 *                  // ...
 *              ]
 *          ],
 *          'requestedServiceName2' => [
 *
 *          ],
 *      ]
 *  ]
 * </code>
 *
 * Class SqsAdapterAbstractFactory
 * @package rollun\callback\Queues\Factory
 */
class SqsAdapterAbstractFactory implements AbstractFactoryInterface
{
    const KEY_PRIORITY_HANDLER = 'priorityHandler';

    const KEY_SQS_CLIENT_CONFIG = 'sqsClientConfig';

    const KEY_SQS_ATTRIBUTES = 'sqsAttributes';

    const KEY_DEAD_LATTER_QUEUE_NAME = 'deadLetterQueueName';

    const KEY_MAX_RECEIVE_COUNT = 'maxReceiveCount';

    const DEF_MAX_RECEIVE_COUNT = 10;

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @return bool
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        return !empty($container->get('config')[self::class][$requestedName]);
    }

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return SQSAdapter
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $serviceConfig = $container->get('config')[self::class][$requestedName];

        if (isset($serviceConfig[self::KEY_PRIORITY_HANDLER])) {
            if (!$container->has($serviceConfig[self::KEY_PRIORITY_HANDLER])) {
                throw new InvalidArgumentException("Invalid option '" . self::KEY_PRIORITY_HANDLER . "'");
            } else {
                $priorityHandler = $container->get($serviceConfig[self::KEY_PRIORITY_HANDLER]);
            }
        } else {
            $priorityHandler = $container->get(StandardPriorityHandler::class);
        }

        if (!isset($serviceConfig[self::KEY_SQS_CLIENT_CONFIG])) {
            throw new InvalidArgumentException("Invalid option '" . self::KEY_SQS_CLIENT_CONFIG . "'");
        }

        $sqsClient = SqsClient::factory($serviceConfig[self::KEY_SQS_CLIENT_CONFIG]);
        $attributes = $serviceConfig[self::KEY_SQS_ATTRIBUTES] ?? [];

        if (isset($serviceConfig[self::KEY_MAX_RECEIVE_COUNT])
            && !isset($serviceConfig[self::KEY_DEAD_LATTER_QUEUE_NAME])) {
            throw new InvalidArgumentException("You forget specify dead letter queue name");
        }

        if (isset($serviceConfig[self::KEY_DEAD_LATTER_QUEUE_NAME])) {
            $queues = $sqsClient->listQueues();
            $results = $queues->get('QueueUrls') ?? [];
            $exist = false;

            foreach ($results as $result) {
                $result = explode('/', $result);
                $queueName = array_pop($result);

                if ($queueName == $serviceConfig[self::KEY_DEAD_LATTER_QUEUE_NAME]) {
                    $exist = true;
                }
            }

            if (!$exist) {
                $sqsClient->createQueue([
                    'QueueName' => $serviceConfig[self::KEY_DEAD_LATTER_QUEUE_NAME],
                    'Attributes' => [],
                ]);
            }

            $maxExecuteTime = 120;
            $endTime = $maxExecuteTime + time();
            $success = false;

            while (!$success) {
                if ($endTime <= time()) {
                    throw new \RuntimeException("Too much time executing");
                }

                try {
                    $queueUrl = $sqsClient->getQueueUrl([
                        'QueueName' => $serviceConfig[self::KEY_DEAD_LATTER_QUEUE_NAME],
                    ])->get('QueueUrl');
                    $deadLetterTargetArn = $sqsClient->getQueueArn($queueUrl);
                    $success = true;
                } catch (\Throwable $e) {
                    $success = false;
                    sleep(1);
                }
            }

            $redrivePolicy = json_encode([
                'maxReceiveCount' => $serviceConfig[self::KEY_MAX_RECEIVE_COUNT] ??
                    self::DEF_MAX_RECEIVE_COUNT,
                'deadLetterTargetArn' => $deadLetterTargetArn
            ]);
            $attributes['RedrivePolicy'] = $redrivePolicy;
        }

        return new SQSAdapter($serviceConfig[self::KEY_SQS_CLIENT_CONFIG], $priorityHandler, $attributes);
    }
}

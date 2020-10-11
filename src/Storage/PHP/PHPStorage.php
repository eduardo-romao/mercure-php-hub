<?php

namespace BenTools\MercurePHP\Storage\PHP;

use BenTools\MercurePHP\Model\Message;
use BenTools\MercurePHP\Model\Subscription;
use BenTools\MercurePHP\Security\TopicMatcher;
use BenTools\MercurePHP\Storage\StorageInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class PHPStorage implements StorageInterface
{
    private int $messagesMaxSize;
    private int $currentMessagesSize = 0;
    private array $messages = [];

    /**
     * @var Subscription[]
     */
    private array $subscriptions = [];
    private ?string $lastEventID = null;

    public function __construct(int $size)
    {
        $this->messagesMaxSize = $size;
    }

    public function getLastEventID(): PromiseInterface
    {
        return resolve($this->lastEventID);
    }

    public function retrieveMessagesAfterID(string $id, array $subscribedTopics): PromiseInterface
    {
        if (self::EARLIEST === $id) {
            return resolve($this->getAllMessages($subscribedTopics));
        }

        return resolve($this->getMessagesAfterId($id, $subscribedTopics));
    }

    public function storeMessage(string $topic, Message $message): PromiseInterface
    {
        if (0 === $this->messagesMaxSize) {
            return resolve(true);
        }

        if ($this->currentMessagesSize >= $this->messagesMaxSize) {
            \array_shift($this->messages);
        }
        $this->messages[] = [$topic, $message];
        $this->currentMessagesSize++;
        $this->lastEventID = $message->getId();

        return resolve(true);
    }

    public function storeSubscriptions(array $subscriptions): PromiseInterface
    {
        foreach ($subscriptions as $subscription) {
            $this->subscriptions[] = $subscription;
        }

        return resolve();
    }

    public function removeSubscriptions(iterable $subscriptions): PromiseInterface
    {
        $subscriptions = \iterable_to_array($subscriptions);
        foreach ($subscriptions as $subscription) {
            foreach ($this->subscriptions as $key => $_subscription) {
                if ($_subscription->getId() === $subscription->getId()) {
                    unset($this->subscriptions[$key]);
                }
            }
        }

        return resolve();
    }

    public function findSubscriptions(?string $topic = null, ?string $subscriber = null): PromiseInterface
    {
        return resolve($this->filterSubscriptions($topic, $subscriber));
    }

    private function filterSubscriptions(?string $topic, ?string $subscriber): iterable
    {
        foreach ($this->subscriptions as $subscription) {
            $matchSubscriberPattern = TopicMatcher::matchesTopicSelectors(
                $subscription->getSubscriber(),
                [$subscriber ?? '{subscriber}']
            );
            $matchSubscriber = (null === $subscriber || $matchSubscriberPattern);
            $matchTopicPattern = TopicMatcher::matchesTopicSelectors(
                $subscription->getTopic(),
                [$topic ?? '{topic}']
            );
            $matchTopic = (null === $topic || $matchTopicPattern);
            if ($matchSubscriber && $matchTopic) {
                yield $subscription;
            }
        }
    }

    private function getMessagesAfterId(string $id, array $subscribedTopics): iterable
    {
        $ignore = true;
        foreach ($this->messages as [$topic, $message]) {
            if ($message->getId() === $id) {
                $ignore = false;
                continue;
            }
            if ($ignore || !TopicMatcher::matchesTopicSelectors($topic, $subscribedTopics)) {
                continue;
            }
            yield $topic => $message;
        }
    }

    private function getAllMessages(array $subscribedTopics): iterable
    {
        foreach ($this->messages as [$topic, $message]) {
            if (!TopicMatcher::matchesTopicSelectors($topic, $subscribedTopics)) {
                continue;
            }
            yield $topic => $message;
        }
    }
}

<?php
namespace FlowThread;

class EchoHook {

	public static function onBeforeCreateEchoEvent(&$notifications, &$notificationCategories, &$icons) {
		$notificationCategories['flowthread'] = array(
			'priority' => 4,
			'tooltip' => 'echo-pref-tooltip-flowthread',
		);
		$notifications['flowthread_reply'] = array(
			'primary-link' => array('message' => 'notification-link-text-view-flowthread_reply', 'destination' => 'title'),
			'category' => 'flowthread',
			'group' => 'interactive',
			'section' => 'message',
			'formatter-class' => 'FlowThread\\EchoReplyFormatter',
			'title-message' => 'notification-flowthread_reply',
			'title-params' => array('agent', 'title'),
			'flyout-message' => 'notification-flowthread_reply-flyout',
			'flyout-params' => array('agent', 'title'),
			'payload' => array('text'),
			'email-subject-message' => 'notification-flowthread_reply-email-subject',
			'email-subject-params' => array('agent'),
			'email-body-batch-message' => 'notification-flowthread_reply-email-batch-body',
			'email-body-batch-params' => array('agent', 'title'),
			'icon' => 'chat',
		);
		$notifications['flowthread_userpage'] = array(
			'primary-link' => array('message' => 'notification-link-text-view-flowthread_userpage', 'destination' => 'title'),
			'category' => 'flowthread',
			'group' => 'interactive',
			'section' => 'message',
			'formatter-class' => 'FlowThread\\EchoReplyFormatter',
			'title-message' => 'notification-flowthread_userpage',
			'title-params' => array('agent', 'title'),
			'flyout-message' => 'notification-flowthread_userpage-flyout',
			'flyout-params' => array('agent', 'title'),
			'payload' => array('text'),
			'email-subject-message' => 'notification-flowthread_userpage-email-subject',
			'email-subject-params' => array('agent'),
			'email-body-batch-message' => 'notification-flowthread_userpage-email-batch-body',
			'email-body-batch-params' => array('agent', 'title'),
			'icon' => 'chat',
		);
		return true;
	}

	public static function onEchoGetDefaultNotifiedUsers($event, &$users) {
		switch ($event->getType()) {
		case 'flowthread_reply':
		case 'flowthread_userpage':
			$extra = $event->getExtra();
			if (!$extra || !isset($extra['target-user-id'])) {
				break;
			}
			$recipientId = $extra['target-user-id'];
			foreach ($recipientId as $id) {
				$recipient = \User::newFromId($id);
				$users[$id] = $recipient;
			}
			break;
		}
		return true;
	}

	public static function onFlowThreadPosted($post) {
		$poster = \User::newFromId($post->userid);
		$title = \Title::newFromId($post->pageid);

		$targets = array();
		$parent = $post->getParent();
		for (; $parent; $parent = $parent->getParent()) {
			// If the parent post is anonymous, we generate no message
			if ($parent->userid === 0) {
				continue;
			}
			// If the parent is the user himself, we generate no message
			if ($parent->userid === $post->userid) {
				continue;
			}
			$targets[] = $parent->userid;
		}
		\EchoEvent::create(array(
			'type' => 'flowthread_reply',
			'title' => $title,
			'extra' => array(
				'target-user-id' => $targets,
				'postid' => $post->id->getBin(),
			),
			'agent' => $poster,
		));

		// Check if posted on a user page
		if ($title->getNamespace() === NS_USER && !$title->isSubpage()) {
			$user = \User::newFromName($title->getText());
			// If user exists and is not the poster
			if ($user && $user->getId() !== 0 && !$user->equals($poster) && !in_array($user->getId(), $targets)) {
				\EchoEvent::create(array(
					'type' => 'flowthread_userpage',
					'title' => $title,
					'extra' => array(
						'target-user-id' => array($user->getId()),
						'postid' => $post->id->getBin(),
					),
					'agent' => $poster,
				));
			}
		}

		return true;
	}

}

class EchoReplyFormatter extends \EchoBasicFormatter {
	protected function formatPayload($payload, $event, $user) {
		switch ($payload) {
		case 'text':
			try {
				return Post::newFromId(UUID::fromBin($event->getExtraParam('postid')))->text;
			} catch (\Exception $e) {
				return wfMessage('notification-flowthread-payload-error');
			}
		default:
			return parent::formatPayload($payload, $event, $user);
			break;
		}
	}
}

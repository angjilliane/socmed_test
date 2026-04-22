<?php 

namespace Drupal\post_edit\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LoginRedirectSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'onResponse',
    ];
  }

  public function onResponse(ResponseEvent $event) {
    $request = $event->getRequest();

    if ($request->getPathInfo() == '/user/login' && \Drupal::currentUser()->isAuthenticated()) {
      $response = new \Symfony\Component\HttpFoundation\RedirectResponse('/posts');
      $event->setResponse($response);
    }
  }
}
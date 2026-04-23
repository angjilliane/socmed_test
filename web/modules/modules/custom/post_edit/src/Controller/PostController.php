<?php

namespace Drupal\post_edit\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\Entity\User;

class PostController {

  public function getData(Request $request) {
      
    $page = (int) $request->query->get('page', 1);
    $limit = 5;
    $offset = ($page - 1) * $limit;

    // Query posts
    $query = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'post')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range($offset, $limit);
    
    $nids = $query->execute();

    if (empty($nids)) {
      return new JsonResponse([]);
    }

    $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

    $data = [];

    
    foreach ($nodes as $node) {
      
      $uid = $node->uid->target_id;
      $author = User::load($uid);

      $avatar = '/themes/custom/product_theme/images/default-avatar.png';

      if ($author) {
        if ($author->hasField('field_profile_image') && !$author->get('field_profile_image')->isEmpty()) {
          $file = File::load($author->get('field_profile_image')->target_id);
          if ($file) {
            $avatar = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }

        elseif ($author->hasField('user_picture') && !$author->get('user_picture')->isEmpty()) {
          $file = File::load($author->get('user_picture')->target_id);
          if ($file) {
            $avatar = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }
      }

      $image_url = null;
      if (!$node->get('field_image')->isEmpty()) {
        $file = $node->get('field_image')->entity;
        $image_url = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($file->getFileUri());
      }

      $data[] = [
        'id' => $node->id(),
        'autho_ful' =>  $author,
        'body' => $node->get('body')->value,
        'author' => $node->getOwner()->getDisplayName(),
        'created' => date('M d, Y H:i', $node->getCreatedTime()),
        'image' => $image_url,
        'avatar' => $avatar,
        'is_owner' => $node->getOwnerId() == \Drupal::currentUser()->id(),
      ];
    }

    return new JsonResponse($data);
  }

  public function create(Request $request) {

    $body = $request->request->get('body');
    $image_url = null;
    if (empty($body)) {
      return new JsonResponse(['error' => 'Empty post'], 400);
    }

    $title = substr(trim(strip_tags($body)), 0, 50);

    $node = \Drupal\node\Entity\Node::create([
      'type' => 'post',
      'uid' => \Drupal::currentUser()->id(),
      'title' => substr(strip_tags($body), 0, 50),
      'body' => [
        'value' => $body,
        'format' => 'basic_html',
      ],
    ]);
  
    $file = $request->files->get('image');
  
    if ($file) {
      $file_system = \Drupal::service('file_system');
  
      $destination = 'public://' . $file->getClientOriginalName();
  
      $file_path = $file_system->copy(
        $file->getRealPath(),
        $destination,
        \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME
      );
  
      $file_entity = \Drupal\file\Entity\File::create([
        'uri' => $file_path,
        'status' => 1,
      ]);
  
      $file_entity->save();
  
      $node->set('field_image', [
        'target_id' => $file_entity->id(),
      ]);
    
      $image_url = \Drupal::service('file_url_generator')
        ->generateAbsoluteString($file_entity->getFileUri());
    }
  
    $node->save();


    $user = \Drupal::currentUser();
    $uid = $user->id();
    $author = User::load($uid);

    $avatar = '/themes/custom/product_theme/images/default-avatar.png';

    if ($author) {
      if ($author->hasField('field_profile_image') && !$author->get('field_profile_image')->isEmpty()) {
        $file = File::load($author->get('field_profile_image')->target_id);
        if ($file) {
          $avatar = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        }
      }

      elseif ($author->hasField('user_picture') && !$author->get('user_picture')->isEmpty()) {
        $file = File::load($author->get('user_picture')->target_id);
        if ($file) {
          $avatar = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        }
      }
    }
  
    return new JsonResponse([
      'id' => $node->id(),
      'author' =>$user->getDisplayName(),
      'created' => date('M d, Y H:i', $node->getCreatedTime()),
      'body' => $node->get('body')->value,
      'image' => $image_url,
      'avatar' => $avatar,
      'is_owner' => true,
    ]);
  }

  public function update(Request $request) {

    $nid = $request->get('nid');
    $body = $request->get('body');
  
    $node = Node::load($nid);
  
    $image_url = '';
  
    if ($node && $node->getOwnerId() == \Drupal::currentUser()->id()) {
  
      $node->set('body', [
        'value' => $body,
        'format' => 'basic_html',
      ]);
  
      $file = $request->files->get('image');
  
      if ($file) {
  
        $file_system = \Drupal::service('file_system');
  
        $destination = 'public://' . $file->getClientOriginalName();
  
        $file_path = $file_system->copy(
          $file->getRealPath(),
          $destination,
          FileSystemInterface::EXISTS_RENAME
        );
  
        $file_entity = File::create([
          'uri' => $file_path,
          'status' => 1,
        ]);
  
        $file_entity->save();
  
        $node->set('field_image', [
          'target_id' => $file_entity->id(),
        ]);
  
        $image_url = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($file_entity->getFileUri());
      }
  
      $node->save();
    }
  
    return new JsonResponse([
      'status' => 'ok',
      'body' => $body,
      'image' => $image_url
    ]);
  }

  public function delete(Request $request) {
    $nid = $request->get('nid');
  
    $node = \Drupal\node\Entity\Node::load($nid);
  
    if ($node && $node->getOwnerId() == \Drupal::currentUser()->id()) {
      $node->delete();
    }
  
    return new JsonResponse(['status' => 'deleted']);
  }
}
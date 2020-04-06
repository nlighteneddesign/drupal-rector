<?php

namespace Drupal\rector_examples;

/**
 * Example of updated static method calls from a class.
 */
class DrupalSetMessageStaticUpdated {

  /**
   * A simple example using the minimum number of arguments.
   *
   * @return null
   */
  public function simple_example() {
    \Drupal::messenger()->addStatus('example message');

    return NULL;
  }

  /**
   * An example using all of the arguments.
   *
   * @return null
   */
  public function using_all_arguments() {
    \Drupal::messenger()->addStatus('example warning', TRUE);

    return NULL;
  }

  /**
   * Examples that show situations where we define the type of message.
   *
   * @return null
   */
  public function message_types() {
    \Drupal::messenger()->addError('example error');

    \Drupal::messenger()->addStatus('example status');

    \Drupal::messenger()->addWarning('example warning');

    return NULL;
  }

  /**
   * This shows using a variable as the message type.
   *
   * This is rare, but used in Devel.
   *
   * @return null
   */
  public function message_type_as_variable() {
    $message = 'example message from variable';

    $type = 'warning';

    switch($type) {
      case 'warning':
        \Drupal::messenger()->addWarning($message);
        break;
      case 'error':
        \Drupal::messenger()->addError($message);
        break;
      default:
        \Drupal::messenger()->addStatus($message);
    }

    return NULL;
  }

}

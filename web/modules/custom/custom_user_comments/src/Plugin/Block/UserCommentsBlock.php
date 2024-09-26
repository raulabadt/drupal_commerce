<?php

namespace Drupal\custom_user_comments\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a custom block that displays user comments statistics.
 *
 * @Block(
 *   id = "user_comments_block",
 *   admin_label = @Translation("User Comments Block")
 * )
 */
class UserCommentsBlock extends BlockBase implements ContainerFactoryPluginInterface {


  /**
   * An instance of entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * An instance of request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new UserCommentsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Injected EntityType.
   * @param Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Injected RequestStack.
   */
  
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get current user or user from the URL.
    $user = $this->requestStack->getCurrentRequest()->query->get('user');
    if (!$user instanceof AccountInterface) {
      $user = \Drupal::currentUser();
    }

    $uid = $user->id();

    // Fetch total comments by the user.
    $total_comments = $this->getTotalCommentsByUser($uid);

    // Fetch last 5 comments and related node titles.
    $last_comments = $this->getLastCommentsByUser($uid);

    // Fetch total words in all comments by the user.
    $total_words = $this->getTotalWordsInComments($uid);

    // Devuelve el render array con un item_list y
    // Asegura que #items sea un array correcto.
    return [
      '#theme' => 'item_list',
      '#items' => array_merge([
        $this->t('Total comments: @count', ['@count' => $total_comments]),
        $this->t('Total words in comments: @count', ['@count' => $total_words]),
        $this->t('Last 5 comments:'),
      ], $last_comments),
    ];

  }

  /**
   * Get the total number of comments by the user using EntityQuery.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return int
   *   The total number of comments.
   */
  protected function getTotalCommentsByUser($uid) {
    // Crear una query de entidad para los comentarios.
    $query = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->condition('uid', $uid)
    // Desactivar la verificación de acceso.
      ->accessCheck(FALSE);

    // Contar cuántos comentarios tiene el usuario.
    return $query->count()->execute();
  }

  /**
   * Get the last 5 comments.
   *
   * Get the last 5 comments and associated
   * node titles by the user using EntityQuery.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return array
   *   An array of comments with node titles.
   */
  protected function getLastCommentsByUser($uid) {
    // Crear una query de entidad para los comentarios.
    $query = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->condition('uid', $uid)
    // Desactivar la verificación de acceso.
      ->accessCheck(FALSE)
      ->sort('created', 'DESC')
      ->range(0, 5);

    // Obtener los IDs de los comentarios.
    $cids = $query->execute();

    $comments = [];

    if (!empty($cids)) {
      // Cargar las entidades de comentarios.
      $comment_entities = $this->entityTypeManager->getStorage('comment')->loadMultiple($cids);

      foreach ($comment_entities as $comment) {
        /** @var \Drupal\comment\Entity\Comment $comment */
        // Obtener la entidad relacionada (el nodo).
        $node = $comment->getCommentedEntity();

        if ($node instanceof Node) {
          $comments[] = $this->t('@comment (Node: @title)', [
            '@comment' => $comment->get('subject')->value,
            '@title' => $node->getTitle(),
          ]);
        }
      }
    }

    return $comments;
  }

  /**
   * Get the total number of words in all comments by the user.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return int
   *   The total number of words.
   */
  protected function getTotalWordsInComments($uid) {
    // Crear una query de entidad para los comentarios.
    $query = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->condition('uid', $uid)
    // Desactivar la verificación de acceso.
      ->accessCheck(FALSE);

    // Obtener los IDs de los comentarios.
    $cids = $query->execute();

    $word_count = 0;

    if (!empty($cids)) {
      // Cargar las entidades de comentarios.
      $comment_entities = $this->entityTypeManager->getStorage('comment')->loadMultiple($cids);

      foreach ($comment_entities as $comment) {
        /** @var \Drupal\comment\Entity\Comment $comment */
        // Obtener el valor del campo 'comment_body'.
        $comment_body = $comment->get('comment_body')->value;
        // Contar las palabras del cuerpo del comentario.
        $word_count += str_word_count(strip_tags($comment_body));
      }
    }

    return $word_count;
  }

}

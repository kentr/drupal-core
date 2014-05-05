<?php

/**
 * @file
 * Contains \Drupal\system\Controller\BatchController.
 */

namespace Drupal\system\Controller;

use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Page\DefaultHtmlFragmentRenderer;
use Drupal\Core\Page\HtmlPage;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for batch routes.
 */
class BatchController implements ContainerInjectionInterface {

  /**
   * The fragment rendering service.
   *
   * @var \Drupal\Core\Page\DefaultHtmlFragmentRenderer
   */
  protected $fragmentRenderer;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * Constructs a new BatchController.
   *
   * @param \Drupal\Core\Page\DefaultHtmlFragmentRenderer $html_fragment_renderer
   *   The fragment rendering service.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   */
  public function __construct(DefaultHtmlFragmentRenderer $html_fragment_renderer, TitleResolverInterface $title_resolver) {
    $this->fragmentRenderer = $html_fragment_renderer;
    $this->titleResolver = $title_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('html_fragment_renderer'),
      $container->get('title_resolver')
    );
  }

  /**
   * Returns a system batch page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return mixed
   *   A \Symfony\Component\HttpFoundation\Response object or page element or
   *   NULL.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function batchPage(Request $request) {
    require_once DRUPAL_ROOT . '/core/includes/batch.inc';
    $output = _batch_page($request);

    if ($output === FALSE) {
      throw new AccessDeniedHttpException();
    }
    elseif ($output instanceof Response) {
      return $output;
    }
    elseif (isset($output)) {
      // Force a page without blocks or messages to
      // display a list of collected messages later.
      drupal_set_page_content($output);
      $page = element_info('page');
      $page['#show_messages'] = FALSE;

      // @todo For some reason, _drupal_bootstrap_code()'s call to
      //   file_get_stream_wrappers() to register all stream wrappers is
      //   insufficient in the case of the no-JS version of the batch page:
      //   checking if a needed CSS aggregate already exists in the file system
      //   will fail unless we re-register the stream wrappers here! This may be
      //   a PHP bug.
      file_get_stream_wrappers();

      $page = $this->render($page);

      return $page;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $output, $status_code = 200) {
    if (!isset($output['#title'])) {
      $request = \Drupal::request();
      $output['#title'] = $this->titleResolver->getTitle($request, $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT));
    }
    $page = new HtmlPage('', isset($output['#cache']) ? $output['#cache'] : array(), $output['#title']);

    $page_array = drupal_prepare_page($output);

    $page = $this->fragmentRenderer->preparePage($page, $page_array);

    $page->setBodyTop(drupal_render($page_array['page_top']));
    $page->setBodyBottom(drupal_render($page_array['page_bottom']));
    $page->setContent(drupal_render($page_array));

    $page->setStatusCode($status_code);

    return $page;
  }

}

<?php

namespace DrupalJedi;

use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The CssTreeShaking class
 *
 * Helps you to eliminate the portions of CSS you aren't using.
 * Usually should be used to generate AMP pages,
 * where is the fixed limit for maximum styles size.
 */
class CssTreeShaking {

  /**
   * HTML to manipulate.
   *
   * @var \Symfony\Component\DomCrawler\Crawler
   */
  protected $html;

  /**
   * All parsed styles.
   *
   * @var \DOMElement[]
   */
  protected $styles = [];

  /**
   * Current limit for styles (50kb).
   *
   * @var int
   */
  protected $stylesLimit;

  /**
   * List of already checked selectors.
   *
   * @var bool[]
   */
  protected $checkedSelectors;

  /**
   * CssTreeShaking constructor.
   *
   * @param string $html
   *   Initial HTML that need's to be optimized.
   *
   * @throws \Exception
   */
  public function __construct(string $html, int $stylesLimit = 50000) {
    $this->html = new Crawler($html);
    $this->stylesLimit = $stylesLimit;
  }

  /**
   * Check if current styles should be shaken.
   *
   * @return bool
   */
  public function shouldBeShacken(): bool {
    $totalSize = 0;

    if (empty($this->styles)) {
      $this->extractStyles();
    }

    foreach ($this->styles as $style) {
      $totalSize += \strlen($style->nodeValue);
    }

    return $totalSize >= $this->stylesLimit;
  }

  /**
   * Do the shaking and return the initial HTML with updated styles.
   *
   * @param bool $force
   *   In case of TRUE, styles will be shaken anyway,
   *   otherwise only if the limit is exceeded.
   *
   * @return string
   */
  public function shakeIt(bool $force = FALSE): string {
    $this->extractStyles();

    // No styles found, return the initial HTML.
    if (empty($this->styles)) {
      return (string) $this->html;
    }

    // Styles are fit into the limit. Shaking is not needed.
    if (!($this->shouldBeShacken() || $force)) {
      return (string) $this->html;
    }

    foreach ($this->styles as $style) {
      $cssParser = new Parser($style->nodeValue);
      $parsedCss = $cssParser->parse();

      /** @var \Sabberworm\CSS\RuleSet\DeclarationBlock $declarationBlock */
      foreach ($parsedCss->getAllDeclarationBlocks() as $declarationBlock) {
        /** @var \Sabberworm\CSS\Property\Selector $selector */
        foreach ($declarationBlock->getSelectors() as $selector) {
          $rawSelector = \explode(':', $selector->getSelector())[0];

          // Delete duplicated classes from selector, ex: ".ex-class.ex-class".
          $rawSelector = \preg_replace('/(\.[^\.\ \{\,\:\;]+)\1+/', '$1', $rawSelector);

          if (!isset($this->checkedSelectors[$rawSelector])) {
            $this->checkedSelectors[$rawSelector] = FALSE;

            // Found a dead css, remove the selector.
            if (!$this->html->filter($rawSelector)->count()) {
              $parsedCss->removeDeclarationBlockBySelector($selector, TRUE);
              $this->checkedSelectors[$rawSelector] = TRUE;
            }
          }
          elseif (isset($this->checkedSelectors[$rawSelector]) && $this->checkedSelectors[$rawSelector]) {
            $parsedCss->removeDeclarationBlockBySelector($selector, TRUE);
          }
        }
      }

      $style->nodeValue = $parsedCss->render(OutputFormat::createCompact());
    }

    return $this->html->getNode(0)->parentNode->saveHTML();
  }

  /**
   * Extract custom styles from HTML to process.
   */
  protected function extractStyles(): void {
    if (!empty($this->styles)) {
      // Avoid double extraction.
      return;
    }

    $this->html->filter('style:not([amp-boilerplate])')->each(function ($style) {
      /** @var \Symfony\Component\DomCrawler\Crawler $style */
      $this->styles[] = $style->getNode(0);
    });
  }

}

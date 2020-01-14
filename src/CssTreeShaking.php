<?php

namespace DrupalJedi;

use Sabberworm\CSS\CSSList\CSSBlockList;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The CssTreeShaking class
 *
 * Helps you to eliminate the portions of CSS you aren't using.
 * Usually should be used to generate AMP pages,
 * where is the fixed limit for maximum styles size.
 */
class CssTreeShaking implements ShakingInterface {

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
   * @param int $stylesLimit
   *   Limit for styles size, 50000 by default.
   *
   * @codeCoverageIgnore
   */
  public function __construct(string $html, int $stylesLimit = 50000) {
    $this->html = new Crawler($html);
    $this->stylesLimit = $stylesLimit;
  }

  /**
   * @inheritDoc
   */
  public function shouldBeShacken(): bool {
    $totalSize = 0;

    if (!$this->getStyles()) {
      $this->extractStyles();
    }

    foreach ($this->getStyles() as $style) {
      $totalSize += \strlen($style->nodeValue);
    }

    return $totalSize >= $this->stylesLimit;
  }

  /**
   * @inheritDoc
   */
  public function shakeIt(bool $force = FALSE): string {
    $this->extractStyles();

    // No styles found, return the initial HTML.
    if (!$this->getStyles()) {
      return $this->exportHtml();
    }

    // Styles are fit into the limit. Shaking is not needed.
    if (!($this->shouldBeShacken() || $force)) {
      return $this->exportHtml();
    }

    foreach ($this->getStyles() as $style) {
      $cssParser = new Parser($style->nodeValue);
      $parsedCss = $cssParser->parse();

      $this->processStyles($parsedCss);

      $style->nodeValue = $parsedCss->render(OutputFormat::createCompact());
    }

    return $this->exportHtml();
  }

  /**
   * @inheritDoc
   */
  public function exportHtml(): string {
    /** @var \DOMElement $element */
    $element = $this->html->getNode(0);
    $doctype = '<!doctype html>';

    return $doctype . $element->parentNode->saveHTML($element);
  }

  /**
   * Process css blocks.
   *
   * @param \Sabberworm\CSS\CSSList\CSSBlockList $parsedCss
   *   Parsed CSS.
   *
   * @codeCoverageIgnore
   */
  protected function processStyles(CSSBlockList $parsedCss) {
    foreach ($parsedCss->getContents() as $content) {
      if ($content instanceof CSSBlockList) {
        $this->processStyles($content);
        if (!$content->getContents()) {
          $parsedCss->remove($content);
        }
      }
      elseif ($content instanceof DeclarationBlock) {
        $this->processDeclarationBlock($content, $parsedCss);
      }
    }
  }

  /**
   * Process selectors inside block.
   *
   * @param \Sabberworm\CSS\RuleSet\DeclarationBlock $block
   *   CSS block with selectors.
   * @param \Sabberworm\CSS\CSSList\CSSBlockList $parsedCss
   *   Parsed CSS document.
   *
   * @codeCoverageIgnore
   */
  protected function processDeclarationBlock(DeclarationBlock $block, CSSBlockList $parsedCss) {
    if (!$block->getRules()) {
      $parsedCss->remove($block);
    }

    foreach ($block->getSelectors() as $selector) {
      $rawSelector = \explode(':', $selector->getSelector())[0];

      // Delete duplicated classes from selector, ex: ".ex-class.ex-class".
      $rawSelector = \preg_replace('/(\.[^\.\ \{\,\:\;]+)\1+/', '$1', $rawSelector);

      // Found a dead css, remove the selector.
      if (!$this->html->filter($rawSelector)->count()) {
        $block->removeSelector($selector);
      }
    }

    if (!$block->getSelectors()) {
      $parsedCss->remove($block);
    }
  }

  /**
   * @inheritDoc
   *
   * @codeCoverageIgnore
   */
  public function getStyles() {
    return $this->styles;
  }

  /**
   * @inheritDoc
   */
  public function extractStyles() {
    if (!$this->getStyles()) {
      $this->html->filter('style:not([amp-boilerplate])')->each(function ($style) {
        /** @var \Symfony\Component\DomCrawler\Crawler $style */
        $node = $style->getNode(0);
        if ($node->nodeValue) {
          $this->styles[] = $node;
        }
      });
    }
  }

}

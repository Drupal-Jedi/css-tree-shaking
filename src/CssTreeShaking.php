<?php

namespace DrupalJedi;

use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;
use simplehtmldom_1_5\simple_html_dom;
use Sunra\PhpSimple\HtmlDomParser;

/**
*  The CssTreeShaking class
*
*  Helps you to eliminate the portions of CSS you aren't using.
 * Usually should be used to generate AMP pages,
 * where is the fixed limit for maximum styles size.
*/
class CssTreeShaking {

  /**
   * HTML to manipulate.
   *
   * @var \simplehtmldom_1_5\simple_html_dom
   */
  protected $html;

  /**
   * All parsed styles.
   *
   * @var \simplehtmldom_1_5\simple_html_dom_node[]
   */
  protected $styles;

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
  public function __construct(string $html) {
    $this->html = HtmlDomParser::str_get_html($html);

    if (!$this->html instanceof simple_html_dom) {
      throw new \Exception('Invalid HTML');
    }
  }

  /**
   * Do the shaking and return the initial HTML with updated styles.
   *
   * @return string
   */
  public function shakeIt(): string {
    foreach ($this->html->find('style') as $styleNode) {
      // Skip the AMP Boilerplate Code.
      if ($styleNode->getAttribute('amp-boilerplate')) {
        continue;
      }

      $this->styles[] = $styleNode;
    }

    // No styles found, return the initial HTML.
    if (empty($this->styles)) {
      return (string) $this->html;
    }

    foreach ($this->styles as $style) {
      $cssParser = new Parser($style->innertext());
      $parsedCss = $cssParser->parse();

      /** @var \Sabberworm\CSS\RuleSet\DeclarationBlock $declarationBlock */
      foreach ($parsedCss->getAllDeclarationBlocks() as $declarationBlock) {
        /** @var \Sabberworm\CSS\Property\Selector $selector */
        foreach ($declarationBlock->getSelectors() as $selector) {
          $rawSelector = explode(':', $selector->getSelector())[0];

          if (!isset($this->checkedSelectors[$rawSelector])) {
            // Found a dead css, remove the selector.
            if (!$this->html->find($rawSelector)) {
              $parsedCss->removeDeclarationBlockBySelector($selector);
            }

            $this->checkedSelectors[$rawSelector] = TRUE;
          }
        }
      }

      $style->innertext = $parsedCss->render(OutputFormat::createCompact());
    }

    return (string) $this->html;
  }

}

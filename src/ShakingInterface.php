<?php

namespace DrupalJedi;

/**
 * Interface ShakingInterface
 */
interface ShakingInterface {

  /**
   * Check if current styles should be shaken.
   *
   * @return bool
   */
  public function shouldBeShacken(): bool;

  /**
   * Do the shaking and return the initial HTML with updated styles.
   *
   * @param bool $force
   *   In case of TRUE, styles will be shaken anyway,
   *   otherwise only if the limit is exceeded.
   *
   * @return string
   */
  public function shakeIt(bool $force = FALSE): string;

  /**
   * Extract custom styles from HTML to process.
   */
  public function extractStyles();

}

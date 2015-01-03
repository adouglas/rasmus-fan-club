<?php

namespace Rasmus\PackageMateBundle\Model;
/**
 * A simple class storing flags to be used in signaling the status of a solution
 */
class BFSOutcome {
  /**
   *	A solution has be found from $start to $end
   */
  const WHOLE_SOLUTION = 1;
  /**
   * Only part of the solution has been identified
   */
  const PART_SOLUTION = 2;
}

<?php
namespace Mrimann\XliffTranslator\ViewHelpers;

/*                                                                        *
 * This script belongs to the FLOW3 package "Mrimann.XliffTranslator".    *
 * Code borrowed from https://github.com/mrimann/topic-voting/            *
 *                                                                        */

use Doctrine\ORM\Mapping as ORM;
use TYPO3\Flow\Annotations as Flow;

/**
 * View helper which renders the flash messages (if there are any). The view helper
 * is heavily inspired by the one that ships with FLOW3 by default. To use the CSS
 * stuff from the Bootstrap CSS framework, we're building different HTML output.
 *
 * @Flow\Scope("prototype")
 */
class FlashMessagesViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper {

	/**
	 * @var string
	 */
	protected $tagName = 'div';

	/**
	 * Initialize arguments
	 *
	 * @return void
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 */
	public function initializeArguments() {
		$this->registerUniversalTagAttributes();
	}

	/**
	 * Render method.
	 *
	 * @return string rendered Flash Messages, if there are any.
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 * @author Mario Rimann <mario@rimann.org>
	 */
	public function render() {
		$flashMessages = $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush();
		if (count($flashMessages) > 0) {
			$tagContent = '';
			foreach ($flashMessages as $singleFlashMessage) {

				// set the severity
				$severity = 'alert-info';

				switch ($singleFlashMessage->getSeverity()) {
					case 'Notice':	$severity = 'alert-info';
						break;
					case 'Information':	$severity = 'alert-info';
						break;
					case 'OK':	$severity = 'alert-success';
						break;
					case 'Warning': $severity = 'alert-warning';
						break;
					case 'Error': $severity = 'alert-error';
						break;
				}

				// check if there's a title and use it then
				$title = '';
				if ($singleFlashMessage->getTitle() != '') {
					$title = '<strong>' . $singleFlashMessage->getTitle() . '</strong>&nbsp;';
				}

				// put it all together
				$tagContent .= '<div class="alert ' . $severity . '"><strong>' . $title . '</strong>' .
					$singleFlashMessage->getMessage()  . '</div>';
			}

			$this->tag->setContent($tagContent);
			return $this->tag->render();
		}
		return '';
	}
}
?>
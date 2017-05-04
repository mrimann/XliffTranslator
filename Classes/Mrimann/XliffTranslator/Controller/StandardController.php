<?php
namespace Mrimann\XliffTranslator\Controller;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Mrimann.XliffTranslator".*
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Standard controller for the Mrimann.XliffTranslator package
 *
 * @Flow\Scope("singleton")
 */
class StandardController extends \TYPO3\Flow\Mvc\Controller\ActionController {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 */
	protected $packageManager;

	/**
	 * @Flow\Inject
	 * @var \Mrimann\XliffTranslator\XliffTranslatorService
	 */
	protected $xliffTranslatorService;

	/**
	 * The index action that shows a list of packages that are available for testing
	 *
	 * @return void
	 */
	public function indexAction() {
		$this->view->assign('packages', $this->xliffTranslatorService->getAvailablePackages());
	}

	/**
	 * Renders the form to translate a specific Xliff file from language A to language B
	 *
	 * @param string $packageKey
	 * @param string $fromLang
	 * @param string $toLang
	 */
	public function translateAction($packageKey = '', $fromLang = '', $toLang = '') {
		$this->view->assign('packageKey', $packageKey);
		$this->view->assign('languages', explode(',', $this->settings['availableLanguages']));
		$this->view->assign('fromLang', $fromLang);
		$this->view->assign('toLang', $toLang);

		// check if we're ready for the actual translation
		if ($fromLang != '' && $toLang != '') {
			$this->view->assign('readyForTranslating', TRUE);
			$this->view->assign('translationMatrix', $this->xliffTranslatorService->generateTranslationMatrix($packageKey, $fromLang, $toLang));
		}
	}

	/**
	 * Saves the translations from the translation form
	 *
	 * @param string $packageKey
	 * @param string $fromLang
	 * @param string $toLang
	 * @param array $translationUnits
	 */
	public function saveTranslationsAction($packageKey, $fromLang, $toLang, array $translationUnits) {
		$matrixToSave = $this->xliffTranslatorService->getTranslationMatrixToSave($packageKey, $fromLang, $toLang, $translationUnits);

		// Create the Xliff file to be written to disk later on
		$xliffView = new \TYPO3\Fluid\View\TemplateView();
		$path = 'resource://Mrimann.XliffTranslator/Private/Templates/Standard/Xliff.xlf';

		$xliffView->setControllerContext($this->getControllerContext());
		$xliffView->setTemplatePathAndFilename($path);
		$xliffView->assign('matrixToSave', $matrixToSave);
		$xliffContent = $xliffView->render();
		$this->xliffTranslatorService->saveXliffFile($packageKey, $toLang, $xliffContent);

		// redirect the user back to the translation page
		$this->addFlashMessage(
			'Your translations were successfully saved!',
			'Yippie!',
			'OK'
		);

		$this->redirect(
			'translate',
			'Standard',
			'Mrimann.XliffTranslator',
			array(
				'packageKey' => $packageKey,
				'fromLang' => $fromLang,
				'toLang' => $toLang
			)
		);
	}
}

?>
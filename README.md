## Why?

In TYPO3 CMS there's a very old but still used extension called `llxmltranslate` that was originally published by Kasper. It enables a Backend user to see the current status of translations for a particular translation file (locallang.php or locallang.xml) per target language.

As an extension developer, this is very helpful to see
- which new labels from the default language have not yet been translated to X
- which labels with translations in language X have been modified in the default language since last translation
- which labels are translated and don't need any update

TYPO3 Flow packages use Xliff files for localizing texts and I was just missing a similar functionality for my packages. Of course, for TYPO3 Flow itself and the official packages, the central Pootle translation server is being used which is fine. But I don't want to set up a Pootle server just to localize some few strings of text so that I can ship my Flow packages pre-translated for at least those languages that I'm able to speak.

The current frontend lets you choose the package and the source/target language combination (remember: EN is not per se the default anymore!) and then shows you a list of translation units like the following:

![Screenshot of the translation tool in action](https://raw.github.com/mrimann/XliffTranslator/master/Documentation/Screenshots/translationView.png)


## How to get started

Given you've a running TYPO3 Flow installation on which you're currently developing an own package, you can just add the XliffTranslator package to your `composer.json` file as an additional requirement (at least for the dev installation), the package key is `mrimann/xlifftranslator` and [the package can be found on packagist.org](https://packagist.org/packages/mrimann/xlifftranslator)

As soon as you've installed this package to your system

- make sure the package you're about to translate has at least the following file `Resources/Private/Translations/en/Main.xlf`
- the file should contain at least one translation unit, see example in this package
- make sure that your package and the XliffTranslator are activated
- browse to http://local-pony/mrimann.xlifftranslator/
- choose your package from the list
- choose the language to which you want to translate
- start translating

Maybe you need to change some of the pre-sets in the settings of the XliffTranslator, the next section will describe how to do so.

Upon clicking the "save translations" button, a copy of a the existing target file is created (into the same directory) for safety reasons and then the new version of the localizations are saved. As you're with some source code versioning tool like e.g. Git (at least I expect you to do so), you'll now see the modifications that the translations have brought up. If you like the result, go ahead and commit them to your package.

## Configuration options

- defaultLanguage: en

	Sets the default language used to check for listing the available packages. If you e.g. use any other language as the default language for your package, set this to your source language.

- availableLanguages: en,de

	Defines the languages for which source/target combinations are built so you can choose which language you want to translate to. This option is the one you'll most probably modify to suit your specific needs.

- packagesToExclude: <comma-separated list of package keys>

	By default all the packages from the Framework are listed here and thus "hidden" from the list of packages that are available for translation. This should shorten the list to a level where only your own package(s) is/are shown.

## How to contribute?

Feel free to [file new issues](https://github.com/mrimann/XliffTranslator/issues) if you find a problem or to propose a new feature. If you want to contribute your time and submit a code change, I'm very eager to look at your pull request!

In case you want to discuss a new feature with me, just send me an e-mail.

## Can I use it in commercial projects?

Yes, please! And if you save some of your precious time with this package, I'd be very happy if you give something back - be it a warm "Thank you" by mail, spending me a drink at a conference, send me a post card or some other surprise :-)

## License

To be decided... (In general: Do whatever you want with it - but don't blame me if you break something)
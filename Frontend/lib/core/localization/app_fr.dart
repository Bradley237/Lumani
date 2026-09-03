import 'app_localizations.dart';

/// French localizations for the Lumani entry experience.
class AppLocalizationsFr extends AppLocalizations {
  const AppLocalizationsFr();

  @override
  String get splashPreparingLearningExperience =>
      'Préparation de votre espace d’apprentissage...';

  // ===========================================================================
  // Onboarding Screen Strings (4-Act Narrative + Controls)
  // ===========================================================================

  @override
  String get onboardingAct1Title =>
      'Apprendre ne devrait pas être si difficile.';

  @override
  String get onboardingAct1Body =>
      "Les examens nationaux sont exigeants, mais les cours dispersés, le manque d'épreuves et l'isolement compliquent inutilement votre préparation.";

  @override
  String get onboardingAct2Title =>
      'Une méthode claire pour maîtriser le programme.';

  @override
  String get onboardingAct2Body =>
      "Accédez à des cours structurés et conformes aux exigences officielles de l'Office du Baccalauréat et du GCE Board.";

  @override
  String get onboardingAct3Title => 'Posez vos questions à tout moment.';

  @override
  String get onboardingAct3Body =>
      "Profitez d'explications détaillées 24h/24 avec le tuteur Lumani IA. Maîtrisez les formules complexes et les dissertations sans attendre.";

  @override
  String get onboardingAct4Title => 'Votre parcours vous mène plus loin.';

  @override
  String get onboardingAct4Body =>
      'Transformez votre réussite scolaire en opportunités réelles : préparez vos filières universitaires et bâtissez votre avenir.';

  @override
  String get onboardingSkip => 'Passer';

  @override
  String get onboardingNext => 'Suivant';

  @override
  String get onboardingGetStarted => 'Commencer';
}

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'locale_cubit.dart';

/// Lumani academic subsystem identifiers.
///
/// These values match the backend [ExamSubsystem] enum exactly:
///   gce  → Anglophone / GCE (O-Level, A-Level)
///   obc  → Francophone / OBC (BEPC, Probatoire, Baccalauréat)
///
/// The UI may display friendly labels such as "GCE" or "OBC", but the
/// internal enum name and the persisted/API value must be 'gce' or 'obc'.
enum Subsystem {
  none,
  gce, // Anglophone — General Certificate of Education
  obc, // Francophone — Office du Baccalauréat du Cameroun
}

extension SubsystemApiValue on Subsystem {
  /// Returns the API/persistence string expected by the backend.
  String get apiValue => switch (this) {
    Subsystem.gce => 'gce',
    Subsystem.obc => 'obc',
    Subsystem.none => 'none',
  };

  static Subsystem fromApiValue(String? value) => switch (value) {
    'gce' => Subsystem.gce,
    'obc' => Subsystem.obc,
    _ => Subsystem.none,
  };
}

// ---------------------------------------------------------------------------
// Exam Level — the cascading academic level within each subsystem
// ---------------------------------------------------------------------------

/// Academic levels available within each [Subsystem].
///
/// GCE track:
///   [ordinaryLevel] — GCE Ordinary Level
///   [advancedLevel] — GCE Advanced Level
///
/// OBC track:
///   [bepc]         — Brevet d'Études du Premier Cycle
///   [probatoire]   — Probatoire
///   [baccalaureat] — Baccalauréat
enum ExamLevel {
  // GCE levels
  ordinaryLevel,
  advancedLevel,

  // OBC levels
  bepc,
  probatoire,
  baccalaureat,
}

extension ExamLevelApiValue on ExamLevel {
  String get apiValue => switch (this) {
    ExamLevel.ordinaryLevel => 'ordinary_level',
    ExamLevel.advancedLevel => 'advanced_level',
    ExamLevel.bepc => 'bepc',
    ExamLevel.probatoire => 'probatoire',
    ExamLevel.baccalaureat => 'bac',
  };

  static ExamLevel? fromApiValue(String? value) => switch (value) {
    'ordinary_level' => ExamLevel.ordinaryLevel,
    'advanced_level' => ExamLevel.advancedLevel,
    'bepc' => ExamLevel.bepc,
    'probatoire' => ExamLevel.probatoire,
    'bac' => ExamLevel.baccalaureat,
    _ => null,
  };

  /// Returns the valid [ExamLevel]s for a given [Subsystem].
  static List<ExamLevel> levelsFor(Subsystem subsystem) => switch (subsystem) {
    Subsystem.gce => [ExamLevel.ordinaryLevel, ExamLevel.advancedLevel],
    Subsystem.obc => [
      ExamLevel.bepc,
      ExamLevel.probatoire,
      ExamLevel.baccalaureat,
    ],
    Subsystem.none => [],
  };
}

// ---------------------------------------------------------------------------
// ExamOptionModel — typed subsystem + level composite
// ---------------------------------------------------------------------------

/// Strongly typed representation of a user's chosen academic track.
///
/// Pairs a [Subsystem] with an optional [ExamLevel] and exposes the
/// valid cascading levels for the chosen subsystem.
class ExamOptionModel extends Equatable {
  final Subsystem subsystem;
  final ExamLevel? selectedLevel;

  const ExamOptionModel({required this.subsystem, this.selectedLevel});

  /// Valid levels the user can select for this subsystem.
  List<ExamLevel> get availableLevels => ExamLevelApiValue.levelsFor(subsystem);

  factory ExamOptionModel.fromJson(Map<String, dynamic> json) {
    return ExamOptionModel(
      subsystem: SubsystemApiValue.fromApiValue(json['subsystem'] as String?),
      selectedLevel: ExamLevelApiValue.fromApiValue(
        json['selected_level'] as String?,
      ),
    );
  }

  Map<String, dynamic> toJson() => {
    'subsystem': subsystem.apiValue,
    'selected_level': selectedLevel?.apiValue,
  };

  ExamOptionModel copyWith({Subsystem? subsystem, ExamLevel? selectedLevel}) {
    return ExamOptionModel(
      subsystem: subsystem ?? this.subsystem,
      selectedLevel: selectedLevel ?? this.selectedLevel,
    );
  }

  @override
  List<Object?> get props => [subsystem, selectedLevel];
}

// ---------------------------------------------------------------------------
// SubsystemState & SubsystemCubit
// ---------------------------------------------------------------------------

class SubsystemState {
  final Subsystem subsystem;
  final bool isInitialized;

  const SubsystemState({required this.subsystem, this.isInitialized = false});

  SubsystemState copyWith({Subsystem? subsystem, bool? isInitialized}) {
    return SubsystemState(
      subsystem: subsystem ?? this.subsystem,
      isInitialized: isInitialized ?? this.isInitialized,
    );
  }
}

class SubsystemCubit extends Cubit<SubsystemState> {
  static const String _subsystemKey = 'user_academic_subsystem';

  final LocaleCubit? localeCubit;

  SubsystemCubit({this.localeCubit})
    : super(const SubsystemState(subsystem: Subsystem.none)) {
    _initSubsystem();
  }

  Future<void> _initSubsystem() async {
    final prefs = await SharedPreferences.getInstance();
    final savedVal = prefs.getString(_subsystemKey);
    final subsystem = SubsystemApiValue.fromApiValue(savedVal);
    emit(state.copyWith(subsystem: subsystem, isInitialized: true));

    // Apply curriculum lock from persisted subsystem on cold start.
    if (subsystem != Subsystem.none) {
      localeCubit?.lockToSubsystem(subsystem.apiValue);
    }
  }

  Future<void> setSubsystem(Subsystem newSubsystem) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_subsystemKey, newSubsystem.apiValue);
    emit(state.copyWith(subsystem: newSubsystem));

    // Enforce curriculum lock: GCE → English, OBC → French.
    localeCubit?.lockToSubsystem(newSubsystem.apiValue);
  }
}

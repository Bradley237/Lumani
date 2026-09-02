import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';

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

  SubsystemCubit() : super(const SubsystemState(subsystem: Subsystem.none)) {
    _initSubsystem();
  }

  Future<void> _initSubsystem() async {
    final prefs = await SharedPreferences.getInstance();
    final savedVal = prefs.getString(_subsystemKey);
    final subsystem = SubsystemApiValue.fromApiValue(savedVal);
    emit(state.copyWith(subsystem: subsystem, isInitialized: true));
  }

  Future<void> setSubsystem(Subsystem newSubsystem) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_subsystemKey, newSubsystem.apiValue);
    emit(state.copyWith(subsystem: newSubsystem));
  }
}

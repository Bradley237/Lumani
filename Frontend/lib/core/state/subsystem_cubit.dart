import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum Subsystem {
  none,
  anglophone, // GCE System
  francophone, // OBC System
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
    if (savedVal == 'anglophone') {
      emit(
        state.copyWith(subsystem: Subsystem.anglophone, isInitialized: true),
      );
    } else if (savedVal == 'francophone') {
      emit(
        state.copyWith(subsystem: Subsystem.francophone, isInitialized: true),
      );
    } else {
      emit(state.copyWith(subsystem: Subsystem.none, isInitialized: true));
    }
  }

  Future<void> setSubsystem(Subsystem newSubsystem) async {
    final prefs = await SharedPreferences.getInstance();
    final strVal = newSubsystem == Subsystem.anglophone
        ? 'anglophone'
        : newSubsystem == Subsystem.francophone
        ? 'francophone'
        : 'none';
    await prefs.setString(_subsystemKey, strVal);
    emit(state.copyWith(subsystem: newSubsystem));
  }
}

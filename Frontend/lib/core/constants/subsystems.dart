enum Subsystem {
  gce,
  obc;

  String get displayName {
    switch (this) {
      case Subsystem.gce:
        return 'Anglophone Subsystem (GCE)';
      case Subsystem.obc:
        return 'Francophone Subsystem (OBC)';
    }
  }

  List<ExamLevel> get examLevels {
    return ExamLevel.values.where((level) => level.subsystem == this).toList();
  }
}

enum ExamLevel {
  oLevel,
  aLevel,
  bepc,
  probatoire,
  baccalaureat;

  Subsystem get subsystem {
    switch (this) {
      case ExamLevel.oLevel:
      case ExamLevel.aLevel:
        return Subsystem.gce;
      case ExamLevel.bepc:
      case ExamLevel.probatoire:
      case ExamLevel.baccalaureat:
        return Subsystem.obc;
    }
  }

  String get displayName {
    switch (this) {
      case ExamLevel.oLevel:
        return 'GCE Ordinary Level';
      case ExamLevel.aLevel:
        return 'GCE Advanced Level';
      case ExamLevel.bepc:
        return 'BEPC';
      case ExamLevel.probatoire:
        return 'Probatoire';
      case ExamLevel.baccalaureat:
        return 'Baccalauréat';
    }
  }
}


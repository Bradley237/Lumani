import 'package:equatable/equatable.dart';

import '../state/subsystem_cubit.dart';

/// Immutable, strongly-typed representation of a Lumani user.
///
/// Replaces all raw `Map<String, dynamic>` user data throughout the auth
/// and state layers. JSON keys match the Laravel backend contract.
class UserModel extends Equatable {
  final int id;
  final String email;
  final String firstName;
  final String lastName;
  final String? phoneNumber;
  final Subsystem activeSubsystem;
  final ExamLevel? academicLevel;
  final int coinBalance;
  final int streakCount;
  final String subscriptionStatus;
  final String preferredLanguage;

  const UserModel({
    required this.id,
    required this.email,
    required this.firstName,
    required this.lastName,
    this.phoneNumber,
    this.activeSubsystem = Subsystem.none,
    this.academicLevel,
    this.coinBalance = 0,
    this.streakCount = 0,
    this.subscriptionStatus = 'free',
    this.preferredLanguage = 'en',
  });

  /// Parses a user JSON object from the Lumani API.
  ///
  /// Handles missing/null fields gracefully with sensible defaults so that
  /// partially populated responses (e.g. right after registration) never crash.
  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      email: json['email'] as String? ?? '',
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      phoneNumber: json['phone_number'] as String?,
      activeSubsystem: SubsystemApiValue.fromApiValue(
        json['exam_system'] as String?,
      ),
      academicLevel: ExamLevelApiValue.fromApiValue(
        json['academic_level'] as String?,
      ),
      coinBalance: (json['coin_balance'] as num?)?.toInt() ?? 0,
      streakCount: (json['streak_count'] as num?)?.toInt() ?? 0,
      subscriptionStatus: json['subscription_status'] as String? ?? 'free',
      preferredLanguage: json['preferred_language'] as String? ?? 'en',
    );
  }

  /// Serializes to the JSON shape expected by the Lumani API.
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'email': email,
      'first_name': firstName,
      'last_name': lastName,
      'phone_number': phoneNumber,
      'exam_system': activeSubsystem.apiValue,
      'academic_level': academicLevel?.apiValue,
      'coin_balance': coinBalance,
      'streak_count': streakCount,
      'subscription_status': subscriptionStatus,
      'preferred_language': preferredLanguage,
    };
  }

  UserModel copyWith({
    int? id,
    String? email,
    String? firstName,
    String? lastName,
    String? phoneNumber,
    Subsystem? activeSubsystem,
    ExamLevel? academicLevel,
    int? coinBalance,
    int? streakCount,
    String? subscriptionStatus,
    String? preferredLanguage,
  }) {
    return UserModel(
      id: id ?? this.id,
      email: email ?? this.email,
      firstName: firstName ?? this.firstName,
      lastName: lastName ?? this.lastName,
      phoneNumber: phoneNumber ?? this.phoneNumber,
      activeSubsystem: activeSubsystem ?? this.activeSubsystem,
      academicLevel: academicLevel ?? this.academicLevel,
      coinBalance: coinBalance ?? this.coinBalance,
      streakCount: streakCount ?? this.streakCount,
      subscriptionStatus: subscriptionStatus ?? this.subscriptionStatus,
      preferredLanguage: preferredLanguage ?? this.preferredLanguage,
    );
  }

  /// Convenience getter — the user's display name.
  String get displayName {
    if (firstName.isEmpty && lastName.isEmpty) return 'Student';
    return '$firstName $lastName'.trim();
  }

  @override
  List<Object?> get props => [
    id,
    email,
    firstName,
    lastName,
    phoneNumber,
    activeSubsystem,
    academicLevel,
    coinBalance,
    streakCount,
    subscriptionStatus,
    preferredLanguage,
  ];
}

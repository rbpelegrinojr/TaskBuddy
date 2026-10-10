class MatchItem {
  const MatchItem({
    required this.rank,
    required this.provider,
    required this.finalScore,
    required this.scores,
    required this.budgetStatus,
    required this.explanation,
  });

  final int rank;
  final MatchProvider provider;
  final double finalScore;
  final MatchScores scores;
  final String budgetStatus;
  final MatchExplanation explanation;

  factory MatchItem.fromJson(Map<String, dynamic> json) {
    return MatchItem(
      rank: json['rank'] as int,
      provider: MatchProvider.fromJson(json['provider'] as Map<String, dynamic>),
      finalScore: (json['final_score'] as num).toDouble(),
      scores: MatchScores.fromJson(json['scores'] as Map<String, dynamic>),
      budgetStatus: json['budget_status'] as String,
      explanation: MatchExplanation.fromJson(
        json['explanation'] as Map<String, dynamic>,
      ),
    );
  }
}

class MatchProvider {
  const MatchProvider({
    required this.id,
    required this.fullName,
    this.headline,
    this.minRate,
    this.yearsExperience,
  });

  final int id;
  final String fullName;
  final String? headline;
  final String? minRate;
  final int? yearsExperience;

  factory MatchProvider.fromJson(Map<String, dynamic> json) {
    return MatchProvider(
      id: json['id'] as int,
      fullName: json['full_name'] as String,
      headline: json['headline'] as String?,
      minRate: json['min_rate'] as String?,
      yearsExperience: json['years_experience'] as int?,
    );
  }
}

class MatchScores {
  const MatchScores({
    required this.category,
    required this.skills,
    required this.availability,
    required this.budget,
  });

  final double category;
  final double skills;
  final double availability;
  final double budget;

  factory MatchScores.fromJson(Map<String, dynamic> json) {
    return MatchScores(
      category: (json['category'] as num).toDouble(),
      skills: (json['skills'] as num).toDouble(),
      availability: (json['availability'] as num).toDouble(),
      budget: (json['budget'] as num).toDouble(),
    );
  }
}

class MatchExplanation {
  const MatchExplanation({
    required this.algorithmVersion,
    required this.finalScore,
    required this.weights,
    required this.category,
    required this.skills,
    required this.availability,
    required this.budget,
    required this.summary,
  });

  final String algorithmVersion;
  final double finalScore;
  final Map<String, dynamic> weights;
  final MatchCategoryExplanation category;
  final MatchSkillsExplanation skills;
  final MatchAvailabilityExplanation availability;
  final MatchBudgetExplanation budget;
  final String summary;

  factory MatchExplanation.fromJson(Map<String, dynamic> json) {
    return MatchExplanation(
      algorithmVersion: json['algorithm_version'] as String,
      finalScore: (json['final_score'] as num).toDouble(),
      weights: Map<String, dynamic>.from(json['weights'] as Map),
      category: MatchCategoryExplanation.fromJson(
        json['category'] as Map<String, dynamic>,
      ),
      skills: MatchSkillsExplanation.fromJson(
        json['skills'] as Map<String, dynamic>,
      ),
      availability: MatchAvailabilityExplanation.fromJson(
        json['availability'] as Map<String, dynamic>,
      ),
      budget: MatchBudgetExplanation.fromJson(
        json['budget'] as Map<String, dynamic>,
      ),
      summary: json['summary'] as String,
    );
  }
}

class MatchCategoryExplanation {
  const MatchCategoryExplanation({required this.matched, required this.score});

  final bool matched;
  final double score;

  factory MatchCategoryExplanation.fromJson(Map<String, dynamic> json) {
    return MatchCategoryExplanation(
      matched: json['matched'] as bool,
      score: (json['score'] as num).toDouble(),
    );
  }
}

class MatchSkillsExplanation {
  const MatchSkillsExplanation({
    required this.score,
    required this.requiredTotal,
    required this.matchedCount,
    required this.matchedSkills,
    required this.missingNonmandatorySkills,
  });

  final double score;
  final int requiredTotal;
  final int matchedCount;
  final List<NamedSkill> matchedSkills;
  final List<NamedSkill> missingNonmandatorySkills;

  factory MatchSkillsExplanation.fromJson(Map<String, dynamic> json) {
    return MatchSkillsExplanation(
      score: (json['score'] as num).toDouble(),
      requiredTotal: json['required_total'] as int,
      matchedCount: json['matched_count'] as int,
      matchedSkills: (json['matched_skills'] as List<dynamic>? ?? const [])
          .map((item) => NamedSkill.fromJson(item as Map<String, dynamic>))
          .toList(),
      missingNonmandatorySkills:
          (json['missing_nonmandatory_skills'] as List<dynamic>? ?? const [])
              .map((item) => NamedSkill.fromJson(item as Map<String, dynamic>))
              .toList(),
    );
  }
}

class MatchAvailabilityExplanation {
  const MatchAvailabilityExplanation({
    required this.available,
    required this.score,
    required this.availableHoursBeforeDeadline,
  });

  final bool available;
  final double score;
  final double availableHoursBeforeDeadline;

  factory MatchAvailabilityExplanation.fromJson(Map<String, dynamic> json) {
    return MatchAvailabilityExplanation(
      available: json['available'] as bool,
      score: (json['score'] as num).toDouble(),
      availableHoursBeforeDeadline:
          (json['available_hours_before_deadline'] as num).toDouble(),
    );
  }
}

class MatchBudgetExplanation {
  const MatchBudgetExplanation({
    required this.status,
    required this.score,
    required this.providerMinRate,
    required this.proposedCompensation,
  });

  final String status;
  final double score;
  final String providerMinRate;
  final String proposedCompensation;

  factory MatchBudgetExplanation.fromJson(Map<String, dynamic> json) {
    return MatchBudgetExplanation(
      status: json['status'] as String,
      score: (json['score'] as num).toDouble(),
      providerMinRate: json['provider_min_rate'] as String,
      proposedCompensation: json['proposed_compensation'] as String,
    );
  }
}

class NamedSkill {
  const NamedSkill({required this.id, required this.name});

  final int id;
  final String name;

  factory NamedSkill.fromJson(Map<String, dynamic> json) {
    return NamedSkill(
      id: json['id'] as int,
      name: json['name'] as String,
    );
  }
}

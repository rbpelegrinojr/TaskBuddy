class Skill {
  const Skill({required this.id, required this.name, this.isMandatory = false});

  final int id;
  final String name;
  final bool isMandatory;

  factory Skill.fromJson(Map<String, dynamic> json) {
    return Skill(
      id: json['id'] as int,
      name: json['name'] as String,
      isMandatory: json['is_mandatory'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toRequestJson() => {
        'skill_id': id,
        'is_mandatory': isMandatory,
      };
}

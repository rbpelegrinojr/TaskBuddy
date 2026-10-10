class Payment {
  const Payment({
    required this.id,
    required this.reference,
    required this.label,
    required this.isSimulated,
    required this.taskId,
    this.taskTitle,
    required this.client,
    required this.provider,
    required this.amount,
    required this.currencyNote,
    required this.state,
    required this.createdAt,
    this.completedAt,
  });

  final int id;
  final String reference;
  final String label;
  final bool isSimulated;
  final int taskId;
  final String? taskTitle;
  final PaymentParty client;
  final PaymentParty provider;
  final String amount;
  final String currencyNote;
  final String state;
  final DateTime createdAt;
  final DateTime? completedAt;

  factory Payment.fromJson(Map<String, dynamic> json) {
    return Payment(
      id: json['id'] as int,
      reference: json['reference'] as String,
      label: json['label'] as String,
      isSimulated: json['is_simulated'] as bool? ?? true,
      taskId: json['task_id'] as int,
      taskTitle: json['task_title'] as String?,
      client: PaymentParty.fromJson(json['client'] as Map<String, dynamic>),
      provider: PaymentParty.fromJson(json['provider'] as Map<String, dynamic>),
      amount: json['amount'] as String,
      currencyNote: json['currency_note'] as String,
      state: json['state'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
      completedAt: json['completed_at'] == null
          ? null
          : DateTime.parse(json['completed_at'] as String),
    );
  }
}

class PaymentParty {
  const PaymentParty({required this.id, this.fullName});

  final int id;
  final String? fullName;

  factory PaymentParty.fromJson(Map<String, dynamic> json) {
    return PaymentParty(
      id: json['id'] as int,
      fullName: json['full_name'] as String?,
    );
  }
}

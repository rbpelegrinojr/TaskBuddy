class AppValidators {
  static String? requiredText(String? value, {String label = 'This field'}) {
    if (value == null || value.trim().isEmpty) {
      return '$label is required.';
    }
    return null;
  }

  static String? email(String? value) {
    final required = requiredText(value, label: 'Email');
    if (required != null) {
      return required;
    }
    final pattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
    if (!pattern.hasMatch(value!.trim())) {
      return 'Enter a valid email address.';
    }
    return null;
  }

  static String? password(String? value) {
    if (value == null || value.isEmpty) {
      return 'Password is required.';
    }
    final hasLetter = RegExp(r'[A-Za-z]').hasMatch(value);
    final hasDigit = RegExp(r'\d').hasMatch(value);
    if (value.length < 8 || value.length > 72 || !hasLetter || !hasDigit) {
      return 'Password must be 8-72 characters and include a letter and a digit.';
    }
    return null;
  }

  static String? futureDeadline(DateTime? value) {
    if (value == null) {
      return 'Deadline is required.';
    }
    if (!value.isAfter(DateTime.now())) {
      return 'Deadline must be in the future.';
    }
    return null;
  }

  static String? positiveMoney(String? value, {String label = 'Compensation'}) {
    final required = requiredText(value, label: label);
    if (required != null) {
      return required;
    }
    final parsed = double.tryParse(value!.trim());
    if (parsed == null || parsed <= 0) {
      return '$label must be greater than 0.';
    }
    return null;
  }

  static String? positiveOptionalNumber(String? value, {String label = 'Value'}) {
    if (value == null || value.trim().isEmpty) {
      return null;
    }
    final parsed = double.tryParse(value.trim());
    if (parsed == null || parsed <= 0) {
      return '$label must be greater than 0.';
    }
    return null;
  }

  static String? disputeReason(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Reason is required.';
    }
    if (value.trim().length < 10) {
      return 'Reason must be at least 10 characters.';
    }
    return null;
  }

  static String? submissionMessage(String? value) {
    if (value != null && value.trim().length > 3000) {
      return 'Note must be 3000 characters or fewer.';
    }
    return null;
  }
}

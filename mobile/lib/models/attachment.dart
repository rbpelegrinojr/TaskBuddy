class Attachment {
  const Attachment({
    required this.id,
    required this.originalName,
    required this.mimeType,
    required this.sizeBytes,
    this.createdAt,
    required this.downloadUrl,
  });

  final int id;
  final String originalName;
  final String mimeType;
  final int sizeBytes;
  final DateTime? createdAt;
  final String downloadUrl;

  factory Attachment.fromJson(Map<String, dynamic> json) {
    return Attachment(
      id: json['id'] as int,
      originalName: json['original_name'] as String,
      mimeType: json['mime_type'] as String,
      sizeBytes: json['size_bytes'] as int,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      downloadUrl: json['download_url'] as String? ?? '',
    );
  }
}

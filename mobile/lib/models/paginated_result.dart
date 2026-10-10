class PaginatedResult<T> {
  const PaginatedResult({required this.items, required this.meta});

  final List<T> items;
  final Map<String, dynamic> meta;
}

import '../core/api/api_client.dart';
import '../models/category.dart';
import '../models/skill.dart';

abstract class CatalogRepository {
  Future<List<Category>> getCategories();
  Future<List<Skill>> getSkills({int? categoryId});
}

class NetworkCatalogRepository implements CatalogRepository {
  NetworkCatalogRepository(this._client);

  final ApiClient _client;

  @override
  Future<List<Category>> getCategories() async {
    final data = await _client.get('categories', requiresAuth: false) as List<dynamic>;
    return data
        .map((item) => Category.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  @override
  Future<List<Skill>> getSkills({int? categoryId}) async {
    final data = await _client.get(
      categoryId == null ? 'skills' : 'categories/$categoryId/skills',
      requiresAuth: false,
    ) as List<dynamic>;
    return data
        .map((item) => Skill.fromJson(item as Map<String, dynamic>))
        .toList();
  }
}

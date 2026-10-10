import 'package:flutter/foundation.dart';

import '../core/api/api_exception.dart';
import '../models/category.dart';
import '../models/skill.dart';
import '../repositories/catalog_repository.dart';
import 'async_state.dart';

class CatalogProvider extends ChangeNotifier {
  CatalogProvider({required CatalogRepository repository}) : _repository = repository;

  final CatalogRepository _repository;

  AsyncState<List<Category>> categoriesState = const AsyncState();
  final Map<int?, AsyncState<List<Skill>>> _skillsStates = {};

  AsyncState<List<Skill>> skillsStateFor(int? categoryId) =>
      _skillsStates[categoryId] ?? const AsyncState();

  Future<void> loadCategories() async {
    categoriesState = AsyncState.loading(categoriesState.data);
    notifyListeners();
    try {
      final categories = await _repository.getCategories();
      categoriesState = categories.isEmpty
          ? AsyncState.empty(categories)
          : AsyncState.success(categories);
    } on ApiException catch (error) {
      categoriesState = AsyncState.error(error.message, categoriesState.data);
    }
    notifyListeners();
  }

  Future<void> loadSkills({int? categoryId}) async {
    _skillsStates[categoryId] = AsyncState.loading(_skillsStates[categoryId]?.data);
    notifyListeners();
    try {
      final skills = await _repository.getSkills(categoryId: categoryId);
      _skillsStates[categoryId] = skills.isEmpty
          ? AsyncState.empty(skills)
          : AsyncState.success(skills);
    } on ApiException catch (error) {
      _skillsStates[categoryId] = AsyncState.error(error.message, _skillsStates[categoryId]?.data);
    }
    notifyListeners();
  }
}

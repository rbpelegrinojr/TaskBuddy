import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/utils/formatters.dart';
import '../../core/utils/validators.dart';
import '../../models/category.dart';
import '../../models/skill.dart';
import '../../providers/catalog_provider.dart';
import '../../providers/requests_provider.dart';
import '../../repositories/requests_repository.dart';
import '../../widgets/app_text_field.dart';
import '../../widgets/async_state_view.dart';
import 'request_detail_screen.dart';

class CreateRequestScreen extends StatefulWidget {
  const CreateRequestScreen({super.key});

  @override
  State<CreateRequestScreen> createState() => _CreateRequestScreenState();
}

class _CreateRequestScreenState extends State<CreateRequestScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _requirementsController = TextEditingController();
  final _compensationController = TextEditingController();
  final _hoursController = TextEditingController();
  final _deadlineController = TextEditingController();
  DateTime? _deadline;
  int? _categoryId;
  bool _exclusive = false;
  PlatformFile? _attachment;
  final Map<int, bool> _selectedSkills = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CatalogProvider>().loadCategories();
    });
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _requirementsController.dispose();
    _compensationController.dispose();
    _hoursController.dispose();
    _deadlineController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final catalog = context.watch<CatalogProvider>();
    final requests = context.watch<RequestsProvider>();
    final categoryState = catalog.categoriesState;
    final skillsState = catalog.skillsStateFor(_categoryId);

    return Scaffold(
      appBar: AppBar(title: const Text('Create request')),
      body: AsyncStateView(
        status: categoryState.status,
        errorMessage: categoryState.errorMessage,
        onRetry: () => catalog.loadCategories(),
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              AppTextField(
                controller: _titleController,
                label: 'Title',
                validator: (value) => AppValidators.requiredText(value, label: 'Title'),
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _descriptionController,
                label: 'Description',
                minLines: 4,
                maxLines: 6,
                validator: (value) {
                  final required = AppValidators.requiredText(value, label: 'Description');
                  if (required != null) return required;
                  if ((value ?? '').trim().length < 10) {
                    return 'Description must be at least 10 characters.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                value: _categoryId,
                decoration: const InputDecoration(
                  labelText: 'Category',
                  border: OutlineInputBorder(),
                ),
                items: (categoryState.data ?? const <Category>[])
                    .map(
                      (category) => DropdownMenuItem<int>(
                        value: category.id,
                        child: Text(category.name),
                      ),
                    )
                    .toList(),
                onChanged: (value) {
                  setState(() {
                    _categoryId = value;
                    _selectedSkills.clear();
                  });
                  if (value != null) {
                    context.read<CatalogProvider>().loadSkills(categoryId: value);
                  }
                },
                validator: (value) => value == null ? 'Category is required.' : null,
              ),
              const SizedBox(height: 12),
              Text('Requested skills', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              AsyncStateView(
                status: _categoryId == null ? categoryState.status : skillsState.status,
                errorMessage: skillsState.errorMessage,
                emptyMessage: _categoryId == null
                    ? 'Choose a category to load skills.'
                    : 'No skills available for this category.',
                onRetry: () => context.read<CatalogProvider>().loadSkills(categoryId: _categoryId),
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: (skillsState.data ?? const <Skill>[]).map((skill) {
                    final selected = _selectedSkills.containsKey(skill.id);
                    final mandatory = _selectedSkills[skill.id] ?? false;
                    return FilterChip(
                      selected: selected,
                      label: Text(selected && mandatory ? '${skill.name} (mandatory)' : skill.name),
                      onSelected: (value) {
                        setState(() {
                          if (!value) {
                            _selectedSkills.remove(skill.id);
                          } else {
                            _selectedSkills[skill.id] = false;
                          }
                        });
                      },
                      avatar: selected
                          ? IconButton(
                              padding: EdgeInsets.zero,
                              constraints: const BoxConstraints.tightFor(width: 24, height: 24),
                              onPressed: () {
                                setState(() {
                                  _selectedSkills[skill.id] = !mandatory;
                                });
                              },
                              icon: Icon(
                                mandatory ? Icons.star : Icons.star_border,
                                size: 18,
                              ),
                            )
                          : null,
                    );
                  }).toList(),
                ),
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _requirementsController,
                label: 'Additional requirements',
                minLines: 2,
                maxLines: 4,
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _deadlineController,
                label: 'Deadline',
                readOnly: true,
                onTap: () => _pickDeadline(),
                validator: (_) => AppValidators.futureDeadline(_deadline),
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _compensationController,
                label: 'Compensation',
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                validator: AppValidators.positiveMoney,
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _hoursController,
                label: 'Estimated hours (optional)',
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                validator: (value) => AppValidators.positiveOptionalNumber(value, label: 'Estimated hours'),
              ),
              const SizedBox(height: 12),
              SwitchListTile(
                value: _exclusive,
                onChanged: (value) => setState(() => _exclusive = value),
                title: const Text('Exclusive assignment required'),
                subtitle: const Text('Prevent overlapping provider commitments while this task is active.'),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () => _pickAttachment(),
                icon: const Icon(Icons.attach_file),
                label: Text(_attachment == null ? 'Choose attachment' : _attachment!.name),
              ),
              const SizedBox(height: 16),
              if ((requests.detailState.errorMessage ?? '').isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(
                    requests.detailState.errorMessage!,
                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                  ),
                ),
              FilledButton(
                onPressed: requests.saving ? null : () => _submit(),
                child: requests.saving
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Create request'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickAttachment() async {
    final result = await FilePicker.platform.pickFiles(withData: false);
    if (result != null && result.files.isNotEmpty) {
      setState(() => _attachment = result.files.single);
    }
  }

  Future<void> _pickDeadline() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      firstDate: now,
      initialDate: _deadline ?? now.add(const Duration(days: 1)),
      lastDate: now.add(const Duration(days: 3650)),
    );
    if (date == null || !mounted) {
      return;
    }
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_deadline ?? now.add(const Duration(hours: 2))),
    );
    if (time == null) {
      return;
    }
    setState(() {
      _deadline = DateTime(date.year, date.month, date.day, time.hour, time.minute);
      _deadlineController.text = AppFormatters.deadlineInputText(_deadline!);
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }
    final payload = RequestUpsertPayload(
      title: _titleController.text,
      description: _descriptionController.text,
      categoryId: _categoryId!,
      skills: _selectedSkills.entries
          .map((entry) => RequestSkillSelection(skillId: entry.key, isMandatory: entry.value))
          .toList(),
      requirements: _requirementsController.text,
      deadline: _deadline!,
      compensation: _compensationController.text,
      estimatedHours: _hoursController.text,
      requiresExclusive: _exclusive,
    );
    final ok = await context.read<RequestsProvider>().createRequest(payload, attachment: _attachment);
    if (!ok || !mounted) {
      return;
    }
    final created = context.read<RequestsProvider>().detailState.data;
    if (created == null) {
      Navigator.of(context).pop();
      return;
    }
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => RequestDetailScreen(requestId: created.id),
      ),
    );
  }
}

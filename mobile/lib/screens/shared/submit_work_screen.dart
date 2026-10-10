import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/utils/validators.dart';
import '../../providers/tasks_provider.dart';
import '../../widgets/app_text_field.dart';

class SubmitWorkScreen extends StatefulWidget {
  const SubmitWorkScreen({super.key, required this.taskId, required this.title});

  final int taskId;
  final String title;

  @override
  State<SubmitWorkScreen> createState() => _SubmitWorkScreenState();
}

class _SubmitWorkScreenState extends State<SubmitWorkScreen> {
  final _formKey = GlobalKey<FormState>();
  final _noteController = TextEditingController();
  final List<PlatformFile> _files = [];

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final tasks = context.watch<TasksProvider>();
    return Scaffold(
      appBar: AppBar(title: Text('Submit work • ${widget.title}')),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              AppTextField(
                controller: _noteController,
                label: 'Note (optional)',
                minLines: 3,
                maxLines: 5,
                validator: AppValidators.submissionMessage,
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () => _pickFiles(),
                icon: const Icon(Icons.upload_file),
                label: Text(_files.isEmpty ? 'Choose files' : '${_files.length} file(s) selected'),
              ),
              ..._files.map((file) => ListTile(title: Text(file.name))),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: tasks.updating ? null : () => _submit(),
                child: tasks.updating
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Submit work'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickFiles() async {
    final result = await FilePicker.platform.pickFiles(allowMultiple: true, withData: false);
    if (result != null) {
      setState(() {
        _files
          ..clear()
          ..addAll(result.files);
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }
    if (_files.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select at least one file.')),
      );
      return;
    }
    final ok = await context.read<TasksProvider>().submitWork(
          taskId: widget.taskId,
          files: _files,
          note: _noteController.text,
        );
    if (ok && mounted) {
      Navigator.of(context).pop();
    }
  }
}

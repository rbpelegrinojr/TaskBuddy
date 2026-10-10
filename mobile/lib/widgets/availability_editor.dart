import 'package:flutter/material.dart';

import '../models/availability_slot.dart';

class AvailabilityEditor extends StatelessWidget {
  const AvailabilityEditor({
    super.key,
    required this.slots,
    required this.onChanged,
  });

  final List<AvailabilitySlot> slots;
  final ValueChanged<List<AvailabilitySlot>> onChanged;

  static const _days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text('Weekly availability', style: Theme.of(context).textTheme.titleMedium),
            const Spacer(),
            OutlinedButton.icon(
              onPressed: () {
                onChanged([
                  ...slots,
                  const AvailabilitySlot(dayOfWeek: 1, startTime: '09:00', endTime: '17:00'),
                ]);
              },
              icon: const Icon(Icons.add),
              label: const Text('Add slot'),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (slots.isEmpty)
          const Text('Add at least one slot so you can be matched.'),
        ...List.generate(slots.length, (index) {
          final slot = slots[index];
          return Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  children: [
                    DropdownButtonFormField<int>(
                      value: slot.dayOfWeek,
                      decoration: const InputDecoration(
                        labelText: 'Day',
                        border: OutlineInputBorder(),
                      ),
                      items: List.generate(
                        _days.length,
                        (dayIndex) => DropdownMenuItem<int>(
                          value: dayIndex,
                          child: Text(_days[dayIndex]),
                        ),
                      ),
                      onChanged: (value) {
                        if (value == null) return;
                        final updated = [...slots];
                        updated[index] = AvailabilitySlot(
                          dayOfWeek: value,
                          startTime: slot.startTime,
                          endTime: slot.endTime,
                        );
                        onChanged(updated);
                      },
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => _pickTime(
                              context,
                              initial: slot.startTime,
                              onSelected: (value) {
                                final updated = [...slots];
                                updated[index] = AvailabilitySlot(
                                  dayOfWeek: slot.dayOfWeek,
                                  startTime: value,
                                  endTime: slot.endTime,
                                );
                                onChanged(updated);
                              },
                            ),
                            child: Text('Start ${slot.startTime}'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => _pickTime(
                              context,
                              initial: slot.endTime,
                              onSelected: (value) {
                                final updated = [...slots];
                                updated[index] = AvailabilitySlot(
                                  dayOfWeek: slot.dayOfWeek,
                                  startTime: slot.startTime,
                                  endTime: value,
                                );
                                onChanged(updated);
                              },
                            ),
                            child: Text('End ${slot.endTime}'),
                          ),
                        ),
                        IconButton(
                          onPressed: () {
                            final updated = [...slots]..removeAt(index);
                            onChanged(updated);
                          },
                          icon: const Icon(Icons.delete_outline),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          );
        }),
      ],
    );
  }

  Future<void> _pickTime(
    BuildContext context, {
    required String initial,
    required ValueChanged<String> onSelected,
  }) async {
    final parts = initial.split(':');
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(
        hour: int.tryParse(parts.first) ?? 9,
        minute: int.tryParse(parts.last) ?? 0,
      ),
    );
    if (picked == null) {
      return;
    }
    final formatted =
        '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}';
    onSelected(formatted);
  }
}

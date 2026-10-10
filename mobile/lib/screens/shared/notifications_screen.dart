import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/notifications_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/notification_tile.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationsProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<NotificationsProvider>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: provider.updating ? null : () => provider.markAllRead(),
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: AsyncStateView(
        status: provider.state.status,
        errorMessage: provider.state.errorMessage,
        emptyMessage: 'No notifications yet.',
        onRetry: () => provider.load(),
        child: ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: provider.state.data?.length ?? 0,
          itemBuilder: (context, index) {
            final item = provider.state.data![index];
            return NotificationTile(
              item: item,
              onTap: () => provider.markRead(item.id),
            );
          },
        ),
      ),
    );
  }
}

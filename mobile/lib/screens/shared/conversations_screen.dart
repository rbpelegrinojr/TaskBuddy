import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/conversations_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/conversation_tile.dart';
import 'chat_screen.dart';

class ConversationsScreen extends StatefulWidget {
  const ConversationsScreen({super.key});

  @override
  State<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends State<ConversationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ConversationsProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ConversationsProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: AsyncStateView(
        status: provider.state.status,
        errorMessage: provider.state.errorMessage,
        emptyMessage: 'No conversations available.',
        onRetry: () => provider.load(),
        child: RefreshIndicator(
          onRefresh: () => provider.load(),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: provider.state.data?.length ?? 0,
            itemBuilder: (context, index) {
              final item = provider.state.data![index];
              return ConversationTile(
                conversation: item,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => ChatScreen(
                      conversationId: item.id,
                      title: item.taskTitle,
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

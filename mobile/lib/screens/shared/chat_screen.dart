import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../providers/chat_provider.dart';
import '../../widgets/async_state_view.dart';

class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key, required this.conversationId, required this.title});

  final int conversationId;
  final String title;

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _controller = TextEditingController();
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await context.read<ChatProvider>().openConversation(widget.conversationId);
      _timer = Timer.periodic(const Duration(seconds: 5), (_) {
        if (mounted) {
          context.read<ChatProvider>().poll();
        }
      });
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    context.read<ChatProvider>().clear();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final chat = context.watch<ChatProvider>();
    final currentUserId = context.watch<AuthProvider>().user?.id;
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: Column(
        children: [
          Expanded(
            child: AsyncStateView(
              status: chat.state.status,
              errorMessage: chat.state.errorMessage,
              emptyMessage: 'No messages yet. Start the conversation.',
              onRetry: () => chat.openConversation(widget.conversationId),
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: chat.state.data?.length ?? 0,
                itemBuilder: (context, index) {
                  final message = chat.state.data![index];
                  final mine = message.senderId == currentUserId;
                  return Align(
                    alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 320),
                      child: Card(
                        color: mine
                            ? Theme.of(context).colorScheme.primaryContainer
                            : Theme.of(context).colorScheme.surfaceContainerHighest,
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Text(message.body),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      minLines: 1,
                      maxLines: 4,
                      decoration: const InputDecoration(
                        labelText: 'Message',
                        border: OutlineInputBorder(),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: chat.sending ? null : () => _send(),
                    child: const Text('Send'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty) {
      return;
    }
    final ok = await context.read<ChatProvider>().send(text);
    if (ok) {
      _controller.clear();
    }
  }
}

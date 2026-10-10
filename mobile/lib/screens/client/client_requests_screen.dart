import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/requests_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/request_card.dart';
import 'create_request_screen.dart';
import 'request_detail_screen.dart';

class ClientRequestsScreen extends StatefulWidget {
  const ClientRequestsScreen({super.key, this.standalone = false});

  final bool standalone;

  @override
  State<ClientRequestsScreen> createState() => _ClientRequestsScreenState();
}

class _ClientRequestsScreenState extends State<ClientRequestsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<RequestsProvider>().loadRequests();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RequestsProvider>();
    final body = RefreshIndicator(
      onRefresh: () => provider.loadRequests(),
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: provider.state.data?.length ?? 0,
        itemBuilder: (context, index) {
          final request = provider.state.data![index];
          return RequestCard(
            request: request,
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => RequestDetailScreen(requestId: request.id),
              ),
            ),
          );
        },
      ),
    );

    final scaffold = Scaffold(
      appBar: widget.standalone ? AppBar(title: const Text('My requests')) : null,
      body: AsyncStateView(
        status: provider.state.status,
        errorMessage: provider.state.errorMessage,
        emptyMessage: 'You have not created any requests yet.',
        onRetry: () => provider.loadRequests(),
        child: body,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.of(context).push(
          MaterialPageRoute<void>(builder: (_) => const CreateRequestScreen()),
        ),
        icon: const Icon(Icons.add),
        label: const Text('New request'),
      ),
    );

    if (widget.standalone) {
      return scaffold;
    }
    return SafeArea(top: false, child: scaffold);
  }
}

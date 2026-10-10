import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/opportunities_provider.dart';
import '../../widgets/async_state_view.dart';
import '../../widgets/request_card.dart';
import '../client/request_detail_screen.dart';

class OpportunitiesScreen extends StatefulWidget {
  const OpportunitiesScreen({super.key, this.standalone = false});

  final bool standalone;

  @override
  State<OpportunitiesScreen> createState() => _OpportunitiesScreenState();
}

class _OpportunitiesScreenState extends State<OpportunitiesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OpportunitiesProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<OpportunitiesProvider>();
    final scaffold = Scaffold(
      appBar: widget.standalone ? AppBar(title: const Text('Opportunities')) : null,
      body: AsyncStateView(
        status: provider.state.status,
        errorMessage: provider.state.errorMessage,
        emptyMessage: 'No matching opportunities are available right now.',
        onRetry: () => provider.load(),
        child: RefreshIndicator(
          onRefresh: () => provider.load(),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: provider.state.data?.length ?? 0,
            itemBuilder: (context, index) {
              final request = provider.state.data![index];
              return RequestCard(
                request: request,
                showMatchSummary: true,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => RequestDetailScreen(requestId: request.id),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
    if (widget.standalone) {
      return scaffold;
    }
    return SafeArea(top: false, child: scaffold);
  }
}

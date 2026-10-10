import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api/api_client.dart';
import 'core/config/api_config.dart';
import 'core/storage/secure_token_storage.dart';
import 'providers/auth_provider.dart';
import 'providers/catalog_provider.dart';
import 'providers/chat_provider.dart';
import 'providers/conversations_provider.dart';
import 'providers/invitations_provider.dart';
import 'providers/matches_provider.dart';
import 'providers/notifications_provider.dart';
import 'providers/opportunities_provider.dart';
import 'providers/payments_provider.dart';
import 'providers/profile_provider.dart';
import 'providers/requests_provider.dart';
import 'providers/tasks_provider.dart';
import 'repositories/auth_repository.dart';
import 'repositories/catalog_repository.dart';
import 'repositories/conversation_repository.dart';
import 'repositories/invitations_repository.dart';
import 'repositories/notifications_repository.dart';
import 'repositories/payments_repository.dart';
import 'repositories/profile_repository.dart';
import 'repositories/requests_repository.dart';
import 'repositories/tasks_repository.dart';
import 'screens/home/home_router.dart';

class TaskBuddyApp extends StatelessWidget {
  const TaskBuddyApp({super.key});

  @override
  Widget build(BuildContext context) {
    final config = ApiConfig.fromEnvironment();
    final storage = SecureTokenStorage();
    final apiClient = ApiClient(config: config, tokenStorage: storage);

    return MultiProvider(
      providers: [
        Provider<ApiConfig>.value(value: config),
        Provider<SecureTokenStorage>.value(value: storage),
        Provider<ApiClient>.value(value: apiClient),
        Provider<AuthRepository>(create: (_) => NetworkAuthRepository(apiClient, storage)),
        Provider<CatalogRepository>(create: (_) => NetworkCatalogRepository(apiClient)),
        Provider<ProfileRepository>(create: (_) => NetworkProfileRepository(apiClient)),
        Provider<RequestsRepository>(create: (_) => NetworkRequestsRepository(apiClient, config)),
        Provider<InvitationsRepository>(create: (_) => NetworkInvitationsRepository(apiClient)),
        Provider<TasksRepository>(create: (_) => NetworkTasksRepository(apiClient, config)),
        Provider<ConversationRepository>(create: (_) => NetworkConversationRepository(apiClient)),
        Provider<PaymentsRepository>(create: (_) => NetworkPaymentsRepository(apiClient)),
        Provider<NotificationsRepository>(create: (_) => NetworkNotificationsRepository(apiClient)),
        ChangeNotifierProvider<AuthProvider>(
          create: (context) {
            final provider = AuthProvider(
              repository: context.read<AuthRepository>(),
            );
            apiClient.setUnauthorizedHandler(() {
              provider.handleUnauthorized();
            });
            provider.restoreSession();
            return provider;
          },
        ),
        ChangeNotifierProvider<CatalogProvider>(
          create: (context) => CatalogProvider(repository: context.read<CatalogRepository>()),
        ),
        ChangeNotifierProvider<ProfileProvider>(
          create: (context) => ProfileProvider(repository: context.read<ProfileRepository>()),
        ),
        ChangeNotifierProvider<RequestsProvider>(
          create: (context) => RequestsProvider(repository: context.read<RequestsRepository>()),
        ),
        ChangeNotifierProvider<MatchesProvider>(
          create: (context) => MatchesProvider(repository: context.read<RequestsRepository>()),
        ),
        ChangeNotifierProvider<OpportunitiesProvider>(
          create: (context) => OpportunitiesProvider(repository: context.read<RequestsRepository>()),
        ),
        ChangeNotifierProvider<InvitationsProvider>(
          create: (context) => InvitationsProvider(repository: context.read<InvitationsRepository>()),
        ),
        ChangeNotifierProvider<TasksProvider>(
          create: (context) => TasksProvider(repository: context.read<TasksRepository>()),
        ),
        ChangeNotifierProvider<ConversationsProvider>(
          create: (context) => ConversationsProvider(repository: context.read<ConversationRepository>()),
        ),
        ChangeNotifierProvider<ChatProvider>(
          create: (context) => ChatProvider(repository: context.read<ConversationRepository>()),
        ),
        ChangeNotifierProvider<PaymentsProvider>(
          create: (context) => PaymentsProvider(repository: context.read<PaymentsRepository>()),
        ),
        ChangeNotifierProvider<NotificationsProvider>(
          create: (context) => NotificationsProvider(repository: context.read<NotificationsRepository>()),
        ),
      ],
      child: MaterialApp(
        title: 'TaskBuddy',
        debugShowCheckedModeBanner: false,
        themeMode: ThemeMode.system,
        theme: ThemeData(
          useMaterial3: true,
          colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal),
        ),
        darkTheme: ThemeData(
          useMaterial3: true,
          colorScheme: ColorScheme.fromSeed(
            seedColor: Colors.teal,
            brightness: Brightness.dark,
          ),
        ),
        home: const HomeRouter(),
      ),
    );
  }
}

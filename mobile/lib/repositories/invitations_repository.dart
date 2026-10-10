import '../core/api/api_client.dart';
import '../models/invitation.dart';
import '../models/paginated_result.dart';

abstract class InvitationsRepository {
  Future<PaginatedResult<Invitation>> list({String? status});
  Future<Invitation> accept(int invitationId);
  Future<Invitation> decline(int invitationId);
}

class NetworkInvitationsRepository implements InvitationsRepository {
  NetworkInvitationsRepository(this._client);

  final ApiClient _client;

  @override
  Future<PaginatedResult<Invitation>> list({String? status}) async {
    final envelope = await _client.getEnvelope('invitations', queryParameters: {
      if (status != null && status.isNotEmpty) 'status': status,
    });
    final data = envelope.data as List<dynamic>;
    return PaginatedResult(
      items: data
          .map((item) => Invitation.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta: envelope.meta,
    );
  }

  @override
  Future<Invitation> accept(int invitationId) async {
    final data = await _client.post('invitations/$invitationId/accept') as Map<String, dynamic>;
    return Invitation.fromJson(data);
  }

  @override
  Future<Invitation> decline(int invitationId) async {
    final data = await _client.post('invitations/$invitationId/decline') as Map<String, dynamic>;
    return Invitation.fromJson(data);
  }
}

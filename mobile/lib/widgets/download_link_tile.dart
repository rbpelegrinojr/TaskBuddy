import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class DownloadLinkTile extends StatelessWidget {
  const DownloadLinkTile({
    super.key,
    required this.title,
    required this.subtitle,
  });

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: const Icon(Icons.attach_file),
      title: Text(title),
      subtitle: Text(subtitle),
      trailing: IconButton(
        tooltip: 'Copy download link',
        onPressed: () async {
          await Clipboard.setData(ClipboardData(text: subtitle));
          if (context.mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Download link copied.')),
            );
          }
        },
        icon: const Icon(Icons.copy),
      ),
    );
  }
}

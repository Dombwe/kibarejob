import 'dart:io';

import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

class CompressionService {
  Future<File> compressImage(File file, {int targetSizeKB = 300}) async {
    if (await file.length() <= targetSizeKB * 1024) {
      return file;
    }

    final tempDir = await getTemporaryDirectory();
    var quality = 85;
    File? output;

    while (quality >= 35) {
      final targetPath = p.join(
        tempDir.path,
        '${p.basenameWithoutExtension(file.path)}_$quality.jpg',
      );
      final compressed = await FlutterImageCompress.compressAndGetFile(
        file.absolute.path,
        targetPath,
        quality: quality,
        minWidth: 1280,
        minHeight: 1280,
        format: CompressFormat.jpeg,
      );

      if (compressed == null) {
        break;
      }

      output = File(compressed.path);
      if (await output.length() <= targetSizeKB * 1024) {
        return output;
      }
      quality -= 10;
    }

    return output ?? file;
  }
}

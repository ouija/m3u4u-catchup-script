<?php
//////////////////////////////////////////////////////////////////////
// m3u4u.com catchup attributes re-injection script v1.0 2024 @ouija
//////////////////////////////////////////////////////////////////////

// URLs for the original and modified playlists
$originalPlaylistUrl = 'http://xtreamcode.ex/get.php?username=xxxx&password=1234&type=m3u_plus&output=ts';  // Original playlist source
$modifiedPlaylistUrl = 'http://m3u4u.com/m3u/xxxx';  // Modified playlist from m3u4u.com


// Local file paths/names
$originalFile = 'original_playlist.m3u';
$modifiedFile = 'modified_playlist.m3u';

// Flag to use local files instead of downloading
$useLocalFiles = false; // Set to true to skip downloading playlists

//////////////////////////////////////////////////////////////////////
// DO NOT EDIT BELOW THIS LINE
//////////////////////////////////////////////////////////////////////

// Function to download a playlist from a URL and save it to the server
function downloadPlaylist($url, $filename) {
	$content = file_get_contents($url);
	if ($content === false) {
		die("Error downloading playlist: $url");
	}
	file_put_contents($filename, $content);
}

// Check if local files exist; if not, download them
if ($useLocalFiles) {
	if (!file_exists($originalFile)) {
		downloadPlaylist($originalPlaylistUrl, $originalFile);
	}
	if (!file_exists($modifiedFile)) {
		downloadPlaylist($modifiedPlaylistUrl, $modifiedFile);
	}
	echo "Using local files for comparison.<br><br>";
} else {
	downloadPlaylist($originalPlaylistUrl, $originalFile);
	downloadPlaylist($modifiedPlaylistUrl, $modifiedFile);
}

// Now proceed with playlist processing logic
$originalPlaylist = file($originalFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$modifiedPlaylist = file($modifiedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Start of updated playlist with url-tvg attribute for EPG from modified playlist (m3u4u.com)
$updatedPlaylist = "#EXTM3U url-tvg=\"" . str_replace('/m3u/', '/epg/', $modifiedPlaylistUrl) . "\"\n";

// Function to extract catchup properties from an EXTINF line
function extractCatchupProperties($extinfLine) {
	$properties = [
		'catchup' => 'not found',
		'catchup-days' => 'not found',
		'catchup-source' => 'not found',
	];

	if (preg_match('/catchup="([^"]+)"/', $extinfLine, $matches)) {
		$properties['catchup'] = $matches[1];
	}
	if (preg_match('/catchup-days="([^"]+)"/', $extinfLine, $matches)) {
		$properties['catchup-days'] = $matches[1];
	}
	if (preg_match('/catchup-source="([^"]+)"/', $extinfLine, $matches)) {
		$properties['catchup-source'] = $matches[1];
	}

	return $properties;
}

// Iterate through modified playlist to find matching URLs
for ($i = 0; $i < count($modifiedPlaylist); $i++) {
	$modifiedLine = $modifiedPlaylist[$i];

	if (strpos($modifiedLine, '#EXTINF') === 0) {
		$modifiedExtInf = $modifiedLine;
		$modifiedUrl = trim($modifiedPlaylist[$i + 1]);

		// Search for matching URL in the original playlist
		$matchingOriginalEntry = false;
		for ($j = 0; $j < count($originalPlaylist); $j++) {
			$originalLine = $originalPlaylist[$j];

			if (trim($originalLine) === $modifiedUrl) {
				// Found a match; get the original EXTINF line and its catchup properties
				$matchingOriginalEntry = $originalPlaylist[$j - 1];
				$catchupProperties = extractCatchupProperties($matchingOriginalEntry);
				break;
			}
		}

		// Build the updated EXTINF line with catchup properties inserted if found
		if ($matchingOriginalEntry) {
			if ($catchupProperties['catchup'] !== 'not found') {
				// Insert catchup properties into the modified EXTINF line
				$updatedExtInf = str_replace(
					',',
					' catchup="' . $catchupProperties['catchup'] . '" catchup-days="' . $catchupProperties['catchup-days'] . '" catchup-source="' . $catchupProperties['catchup-source'] . '",',
					$modifiedExtInf
				);
			} else {
				// No catchup properties found, keep the original modified EXTINF line
				$updatedExtInf = $modifiedExtInf;
			}
		} else {
			// No match found, keep the original modified EXTINF line
			$updatedExtInf = $modifiedExtInf;
		}

		// Add the updated EXTINF line and URL to the new playlist content without extra line break
		$updatedPlaylist .= $updatedExtInf . "\n" . $modifiedUrl . "\n";
		$i++; // Skip next line since it's the URL we already handled
	}
}

// Output the playlist as M3U
header('Content-Type: application/vnd.apple.mpegurl');
header('Content-Disposition: attachment; filename="Custom"');
echo $updatedPlaylist;

// Delete local files if not using local files
if (!$useLocalFiles) {
	if (file_exists($originalFile)) {
		unlink($originalFile);
	}
	if (file_exists($modifiedFile)) {
		unlink($modifiedFile);
	}
}


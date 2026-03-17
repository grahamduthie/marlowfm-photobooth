#!/usr/bin/env python3
"""Delete photos taken after 17:14 on 2026-03-16"""

import json
import os
import subprocess
import sys

METADATA_FILE = '/photos/.metadata.json'
PHOTOS_DIR = '/photos'
CUTOFF_TIME = '17:14:00'
CUTOFF_DATE = '2026-03-16'

def main():
    # Load metadata
    with open(METADATA_FILE, 'r') as f:
        metadata = json.load(f)
    
    # Find tokens to delete
    to_delete = []
    files_to_delete = set()
    
    for token, data in metadata.items():
        created = data.get('created', '')
        if CUTOFF_DATE in created:
            time = created.split(' ')[1] if ' ' in created else ''
            if time > CUTOFF_TIME:
                to_delete.append(token)
                files_to_delete.add(data.get('filename_clean', ''))
                files_to_delete.add(data.get('filename_branded', ''))
    
    if not to_delete:
        print("No photos to delete")
        return
    
    print(f"Deleting {len(to_delete)} photos taken after {CUTOFF_TIME} on {CUTOFF_DATE}")
    print()
    
    # Build list of full paths to delete
    delete_paths = []
    for token in to_delete:
        data = metadata[token]
        date_path = os.path.dirname(data.get('filename_clean', ''))
        if date_path:
            year_month_day = date_path  # e.g., "2026/03/16"
            for fname in [data.get('filename_clean', ''), data.get('filename_branded', '')]:
                if fname:
                    full_path = os.path.join(PHOTOS_DIR, year_month_day, fname)
                    delete_paths.append(full_path)
    
    # Delete files from filesystem
    deleted_files = []
    for path in delete_paths:
        if os.path.exists(path):
            os.remove(path)
            deleted_files.append(path)
            print(f"Deleted: {path}")
    
    # Remove from metadata
    for token in to_delete:
        del metadata[token]
    
    # Save updated metadata
    with open(METADATA_FILE, 'w') as f:
        json.dump(metadata, f, indent=2)
    
    print(f"\nDeleted {len(deleted_files)} files")
    print(f"Removed {len(to_delete)} entries from metadata")
    
    # Sync to remote
    print("\nSyncing to remote server...")
    result = subprocess.run([
        'rsync', '-a', '--quiet', '--delete',
        '--include=*/',
        '--include=*.jpg',
        '--include=.metadata.json',
        '--exclude=thumbs/',
        '--exclude=*',
        f'{PHOTOS_DIR}/',
        'broadcast@10.10.0.165:/var/www/photobooth/photos/'
    ], capture_output=True, text=True)
    
    if result.returncode == 0:
        print("Sync to remote: OK")
    else:
        print(f"Sync to remote: FAILED - {result.stderr}")

if __name__ == '__main__':
    main()

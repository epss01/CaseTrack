"""Check tracked checkpoint paths without opening files or printing contents."""

import os
import re
import subprocess
import sys
from pathlib import PurePosixPath


def forbidden(path):
    parts = PurePosixPath(path.casefold()).parts
    name = parts[-1]
    if any(part in {".agents", ".claude", ".codex", "agents.md", "claude.md"} for part in parts):
        return True
    if name == ".env" or name.startswith(".env."):
        return path not in {".env.example", ".env.production.example", ".env.xampp", ".env.docker"}
    return bool(re.search(r"\.(sql|dump|sqlite3?)(\.(gz|bz2|xz|zst|zip)|-(wal|shm|journal))?$", name))


if __name__ == "__main__":
    tree = subprocess.check_output(["git", "ls-tree", "-rz", "--name-only", "HEAD"])
    paths = (os.fsdecode(path) for path in tree.split(b"\0") if path)
    rejected = [path for path in paths if forbidden(path)]
    for path in rejected:
        print(f"Excluded checkpoint path: {ascii(path)}")
    sys.exit(bool(rejected))

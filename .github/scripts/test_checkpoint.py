import runpy
import unittest
from pathlib import Path


class CheckpointTest(unittest.TestCase):
    def test_submission_paths(self):
        forbidden = runpy.run_path(Path(__file__).with_name("check-checkpoint.py"))["forbidden"]
        for path in ["AGENTS.md", ".claude/hooks/check", "pkg/.codex/config.toml",
                     "nested/.env", ".env.production", "data.sql.gz", "database.sqlite-wal"]:
            with self.subTest(path=path):
                self.assertTrue(forbidden(path))
        for path in [".env.example", ".env.production.example", ".env.xampp",
                     ".env.docker", "app/Models/CaseModel.php", "docs/database.md"]:
            with self.subTest(path=path):
                self.assertFalse(forbidden(path))


if __name__ == "__main__":
    unittest.main()

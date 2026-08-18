#!/usr/bin/env python
# -*- coding: utf_8 -*-
"""Default httptools settings."""

import os
from pathlib import Path
# Proxy and Web GUI
PROXY_HOST = '127.0.0.1'
PROXY_PORT = 8888
BASE_PATH = os.path.dirname(os.path.abspath(__file__))
HOME_DIR = os.path.join(str(Path.home()), '.httptools')
FLOWS_DIR = os.path.join(HOME_DIR, 'flows')

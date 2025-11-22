#!/usr/bin/env python3
"""
Background removal script using rembg library
This script can be called from PHP to process images locally
"""

import sys
import os
from rembg import remove
from PIL import Image
import io
import base64

def process_image_from_file(input_path, output_path):
    """
    Process an image file and save the result
    """
    try:
        # Open the image
        input_image = Image.open(input_path)
        
        # Remove background
        output_image = remove(input_image)
        
        # Save the result
        output_image.save(output_path)
        
        return True, "Success"
    except Exception as e:
        return False, str(e)

def process_image_from_base64(base64_data, output_path):
    """
    Process an image from base64 data and save the result
    """
    try:
        # Decode base64 data
        image_data = base64.b64decode(base64_data)
        
        # Open the image
        input_image = Image.open(io.BytesIO(image_data))
        
        # Remove background
        output_image = remove(input_image)
        
        # Save the result
        output_image.save(output_path)
        
        return True, "Success"
    except Exception as e:
        return False, str(e)

def main():
    """
    Main function to process images
    Usage: python rembg_processor.py <input_path_or_base64> <output_path> [--base64]
    """
    if len(sys.argv) < 3:
        print("Usage: python rembg_processor.py <input_path_or_base64> <output_path> [--base64]")
        sys.exit(1)
    
    input_data = sys.argv[1]
    output_path = sys.argv[2]
    is_base64 = '--base64' in sys.argv
    
    if is_base64:
        success, message = process_image_from_base64(input_data, output_path)
    else:
        # Check if input file exists
        if not os.path.exists(input_data):
            print(f"Error: Input file {input_data} does not exist")
            sys.exit(1)
        success, message = process_image_from_file(input_data, output_path)
    
    if success:
        print(f"Success: {message}")
        sys.exit(0)
    else:
        print(f"Error: {message}")
        sys.exit(1)

if __name__ == "__main__":
    main()
using UnityEngine;
using System.Collections;

public class UserInterface : MonoBehaviour 
{
    private Rect _labelPosition;
    private string _labelText;
    private Rect _whiteDirectionLablePosition;
    private string _whiteDirectionlableText;
    private Rect _angleDifferenceLablePosition;
    private string _angleDifferenceLableText;


    private Vector3 _worldPosOfRayHit;
    private Transform plane;

    private Vector3 _redDirection;
    private Vector3 _whiteDirection;

	// Use this for initialization
	void Start () 
    {
	}
	
	// Update is called once per frame
	void Update () 
    {
        FindPosition();
        CalculateDirectionVector();
        FindAnglerDieffernece();
	}

    void OnGUI()
    {
        _labelPosition = new Rect( 10, 10, 1000, 100 );
        GUI.Label( _labelPosition, _labelText );
        _whiteDirectionLablePosition = new Rect( 10, 30, 1000, 100 );
        GUI.Label( _whiteDirectionLablePosition, _whiteDirectionlableText );
        _angleDifferenceLablePosition = new Rect( 10, 50, 1000, 100 );
        GUI.Label( _angleDifferenceLablePosition, _angleDifferenceLableText );
    }

    void CalculateDirectionVector()
    {
        GameObject sphere1 = GameObject.Find( "Sphere 1" );
        GameObject sphere2 = GameObject.Find( "Sphere 2" );
        GameObject sphere3 = GameObject.Find( "Sphere 3" );

        Vector3 direction = sphere2.transform.position - sphere1.transform.position;
        direction = direction.normalized;

		Vector3 directionToWhite = 
        _redDirection = direction;
        _labelText = string.Format( "Red Direction Vector: X - {0} Y - {1} Z - {2}", direction.x, direction.y, direction.z );

        Vector3 whiteDirection = sphere3.transform.position - sphere1.transform.position;
        whiteDirection = whiteDirection.normalized;

        _whiteDirection = whiteDirection;

        _whiteDirectionlableText = string.Format( "White Direction Vector: X - {0} Y - {1} Z - {2}",
												   whiteDirection.x, whiteDirection.y, whiteDirection.z );

    }

    void FindPosition()
    {
		 if (Input.GetKeyDown(KeyCode.Mouse0))
         {
			 // raycast
             RaycastHit hit;
             Ray ray = Camera.main.ScreenPointToRay( Input.mousePosition );
			
			 if( Physics.Raycast( ray, out hit, 100.0f ) )
			 {
                 GameObject sphere = GameObject.Find( "Sphere 2" );
                 sphere.transform.position = new Vector3( hit.point.x, 0.0f, hit.point.z );
             }
         } 
    }

    void FindAnglerDieffernece()
    {
		GameObject sphere1 = GameObject.Find( "Sphere 1" );
		GameObject sphere2 = GameObject.Find( "Sphere 2" );

        float cosDelta = ( Vector3.Dot( _redDirection, _whiteDirection ) ) * ( _redDirection.magnitude * _whiteDirection.magnitude );
        float delta = Mathf.Acos( cosDelta ) * 57.2957795f;

        delta = ( _redDirection.x < 0 ) ? 360 - delta : delta;
        _angleDifferenceLableText = string.Format( "Angle difference - {0}", delta );

    }
}